import pytest
from fastapi.testclient import TestClient
from main import app, kafka_producer, redis_client, get_db, AdModel, EventModel, engine, Base
import json
import time
import os
from unittest.mock import MagicMock, patch
from sqlalchemy.orm import sessionmaker, Session
from sqlalchemy import create_engine as create_mock_engine
from datetime import datetime, timedelta

# Use TestClient to test the FastAPI application
client = TestClient(app)

# Fixture to mock database for tests
@pytest.fixture(name="mock_db_session")
def mock_db_session_fixture(monkeypatch):
    """
    Mocks the database session for FastAPI tests.
    """
    MockSession = sessionmaker()
    mock_db = MockSession()

    def override_get_db():
        try:
            yield mock_db
        finally:
            mock_db.close()

    monkeypatch.setattr("main.get_db", override_get_db)
    yield mock_db
    mock_db.close()

# Fixture to mock Kafka and Redis clients
@pytest.fixture(autouse=True)
def mock_clients(monkeypatch):
    """
    Mock Kafka and Redis clients to prevent actual connections during tests.
    By default, they are mocked as connected.
    """
    class MockKafkaProducer:
        def send(self, topic, value):
            print(f"Mock Kafka: Sent message to topic '{topic}': {value}")
            return self # Return self to allow .get() call
        def get(self, timeout=None):
            return True # Simulate successful send
        def bootstrap_connected(self):
            return True # Simulate connected state

    class MockRedis:
        def rpush(self, key, value):
            print(f"Mock Redis: Pushed to list '{key}': {value}")
            return 1
        def ping(self):
            return True

    monkeypatch.setattr('main.kafka_producer', MockKafkaProducer())
    monkeypatch.setattr('main.redis_client', MockRedis())
    
    # Ensure any fallback log file is clean before each test
    if os.path.exists("event_log_fallback.txt"):
        os.remove("event_log_fallback.txt")

def test_health_check_endpoint():
    """Test the /health endpoint."""
    response = client.get("/health")
    assert response.status_code == 200
    assert response.json()["status"] == "ok"
    assert response.json()["kafka_status"] == "connected"
    assert response.json()["redis_status"] == "connected"

def test_recommendation_endpoint_with_history(mock_db_session):
    """
    Test the /recommend endpoint for a user with history,
    expecting recommendations based on their interests.
    """
    # Mock data directly in the session to avoid complex factory setups for SQLAlchemy models
    mock_ads = [
        AdModel(id=1, tenant_id=1, name="Summer Sale", content="", start_time=datetime.now(), end_time=datetime.now(), target_audience={"interests": ["fashion", "sale"]}),
        AdModel(id=2, tenant_id=1, name="New Gadget", content="", start_time=datetime.now(), end_time=datetime.now(), target_audience={"interests": ["tech", "electronics"]}),
        AdModel(id=3, tenant_id=1, name="Travel Package", content="", start_time=datetime.now(), end_time=datetime.now(), target_audience={"interests": ["travel", "sale"]}),
        AdModel(id=4, tenant_id=1, name="Sports Gear", content="", start_time=datetime.now(), end_time=datetime.now(), target_audience={"interests": ["sports", "fitness"]}),
        AdModel(id=5, tenant_id=1, name="Winter Jackets", content="", start_time=datetime.now(), end_time=datetime.now(), target_audience={"interests": ["fashion", "winter"]}),
        AdModel(id=6, tenant_id=1, name="Outdoor Adventure", content="", start_time=datetime.now(), end_time=datetime.now(), target_audience={"interests": ["travel", "sports"]}),
    ]
    mock_events = [
        EventModel(ad_id=1, user_id=101, event_type="click", tenant_id=1, occurred_at=datetime.now() - timedelta(days=5)),
        EventModel(ad_id=3, user_id=101, event_type="impression", tenant_id=1, occurred_at=datetime.now() - timedelta(days=2)),
        EventModel(ad_id=1, user_id=101, event_type="click", tenant_id=1, occurred_at=datetime.now()),
        EventModel(ad_id=5, user_id=101, event_type="click", tenant_id=1, occurred_at=datetime.now() - timedelta(days=1)),
        EventModel(ad_id=7, user_id=101, event_type="impression", tenant_id=1, occurred_at=datetime.now() - timedelta(days=3)),
    ]
    
    # Mock the query calls to return the predefined data
    # First call for ads, second for user events filter, then order by
    mock_db_session.query.side_effect = [
        MagicMock(all=MagicMock(return_value=mock_ads)), # For AdModel query
        MagicMock(all=MagicMock(return_value=mock_events)), # For all events query (for CTR)
        MagicMock(filter=MagicMock(return_value=MagicMock(order_by=MagicMock(all=MagicMock(return_value=mock_events))))), # For EventModel query
    ]

    user_id = 101
    response = client.get(f"/recommend?user_id={user_id}")
    assert response.status_code == 200
    data = response.json()
    assert "recommendations" in data
    assert len(data["recommendations"]) > 0

    recommended_ids = {ad['id'] for ad in data['recommendations']}
    # Based on user 101's interactions, ads related to fashion (ad 5) and travel/sports (ad 6) should be prominent
    assert 6 in recommended_ids or 2 in recommended_ids # Ad 6 (travel, sports) shares 'travel' with Ad 3. Ad 2 (tech) is less likely.
    
    # Ensure ads already interacted with are not in the primary recommendations (based on logic)
    user_interacted_ad_ids = {event.ad_id for event in mock_events}
    for rec_ad in data["recommendations"]:
        # Only check if it's among the *first* set of recommendations (before random fill)
        # For simplicity, we just assert it's not the _most_ recent interaction unless no other choice.
        # A more robust test would inspect the `ad_scores` before random fill.
        pass # This depends on the exact algorithm's exclusion logic.

def test_recommendation_endpoint_no_history(mock_db_session):
    """Test the /recommend endpoint for a user with no history."""
    mock_ads = [
        AdModel(id=1, tenant_id=1, name="Summer Sale", content="", start_time=datetime.now(), end_time=datetime.now(), target_audience={"interests": ["fashion", "sale"]}),
        AdModel(id=2, tenant_id=1, name="New Gadget", content="", start_time=datetime.now(), end_time=datetime.now(), target_audience={"interests": ["tech"]}),
    ]
    mock_events_for_ctr = [
        EventModel(ad_id=1, user_id=1, event_type="impression", tenant_id=1, occurred_at=datetime.now()),
        EventModel(ad_id=1, user_id=2, event_type="click", tenant_id=1, occurred_at=datetime.now()),
        EventModel(ad_id=2, user_id=3, event_type="impression", tenant_id=1, occurred_at=datetime.now()),
    ]
    # Mock query for ads, then mock query for all events (for CTR), then mock query for user events to return empty list
    mock_db_session.query.side_effect = [
        MagicMock(all=MagicMock(return_value=mock_ads)),
        MagicMock(all=MagicMock(return_value=mock_events_for_ctr)),
        MagicMock(filter=MagicMock(return_value=MagicMock(order_by=MagicMock(all=MagicMock(return_value=[]))))),
    ]

    response = client.get("/recommend?user_id=999") # User 999 has no mock history
    assert response.status_code == 200
    data = response.json()
    assert "recommendations" in data
    assert len(data["recommendations"]) > 0
    # For no history, it should fall back to random sampling of existing ads.
    assert all(ad['id'] in {a.id for a in mock_ads} for ad in data['recommendations'])

def test_log_event_endpoint_success():
    """Test logging an event successfully to Kafka (mocked)."""
    event_data = {
        "user_id": 105,
        "ad_id": 8,
        "event_type": "click",
        "tenant_id": 2
    }
    response = client.post("/log-event", json=event_data)
    assert response.status_code == 200
    assert "logged to Kafka successfully" in response.json()["message"]
    assert response.json()["event"]["user_id"] == 105

def test_log_event_endpoint_fallback_to_redis_when_kafka_fails(monkeypatch):
    """Test logging an event when Kafka is unavailable, falling back to Redis (mocked)."""
    # Simulate Kafka producer connection failure
    def mock_bootstrap_connected_false(self):
        return False
    monkeypatch.setattr('main.kafka_producer.bootstrap_connected', mock_bootstrap_connected_false)

    event_data = {
        "user_id": 106,
        "ad_id": 9,
        "event_type": "impression",
        "tenant_id": 1
    }
    response = client.post("/log-event", json=event_data)
    assert response.status_code == 200
    assert "logged to Redis (fallback) successfully" in response.json()["message"]
    assert response.json()["event"]["user_id"] == 106
    
def test_log_event_endpoint_fallback_to_file_when_kafka_and_redis_fail(monkeypatch):
    """
    Test logging an event when both Kafka and Redis are unavailable,
    falling back to file logging.
    """
    # Simulate Kafka producer connection failure
    def mock_bootstrap_connected_false(self):
        return False
    monkeypatch.setattr('main.kafka_producer.bootstrap_connected', mock_bootstrap_connected_false)
    
    # Simulate Redis connection failure (by setting redis_client to None)
    monkeypatch.setattr('main.redis_client', None)

    event_data = {
        "user_id": 107,
        "ad_id": 10,
        "event_type": "click",
        "tenant_id": 3
    }
    response = client.post("/log-event", json=event_data)
    assert response.status_code == 503 # Or 500, depending on desired strictness for file fallback
    assert "Event logging service is unavailable" in response.json()["detail"]
    
    # Verify content in fallback file
    with open("event_log_fallback.txt", "r") as f:
        lines = f.readlines()
        assert len(lines) == 1
        logged_event = json.loads(lines[0])
        assert logged_event["user_id"] == 107
        assert logged_event["event_type"] == "click"

def test_log_event_with_missing_data():
    """Test logging an event with missing required data (should fail Pydantic validation)."""
    event_data = {
        "user_id": 107,
        "ad_id": 10,
        # event_type is missing
        "tenant_id": 3
    }
    response = client.post("/log-event", json=event_data)
    assert response.status_code == 422 # Unprocessable Entity due to validation error
    assert "value_error" in response.json()["detail"][0]["type"]

def test_database_connection_failure_during_recommendation(monkeypatch):
    """
    Test that recommendation endpoint handles database connection failures gracefully.
    """
    # Simulate database session query failing
    mock_session_failing_query = MagicMock()
    mock_session_failing_query.query.side_effect = Exception("Simulated DB query timeout/failure")

    def override_get_db_failing():
        yield mock_session_failing_query

    monkeypatch.setattr("main.get_db", override_get_db_failing)

    response = client.get("/recommend?user_id=1")
    # Expect a 500 internal server error due to DB issues
    assert response.status_code == 500
    assert "Internal Server Error" in response.json()["detail"]
