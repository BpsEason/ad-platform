from fastapi import FastAPI, HTTPException, Depends
from pydantic import BaseModel
from typing import List, Optional, Dict, Any
import random
import time
import json
import redis
from kafka import KafkaProducer
import os
import sys

# Import SQLAlchemy components for database integration
from sqlalchemy.orm import Session
from sqlalchemy import create_engine, Column, Integer, String, Text, DateTime, JSON, TIMESTAMP
from sqlalchemy.ext.declarative import declarative_base
from sqlalchemy.orm import sessionmaker
from datetime import datetime, timedelta

# --- Database Configuration (FastAPI's perspective) ---
DB_HOST = os.getenv("DB_HOST", "db")
DB_PORT = os.getenv("DB_PORT", "3306")
DB_DATABASE = os.getenv("DB_DATABASE", "ad_platform_db")
DB_USERNAME = os.getenv("DB_USERNAME", "user")
DB_PASSWORD = os.getenv("DB_PASSWORD", "password")

SQLALCHEMY_DATABASE_URL = f"mysql+pymysql://{DB_USERNAME}:{DB_PASSWORD}@{DB_HOST}:{DB_PORT}/{DB_DATABASE}"

engine = create_engine(
    SQLALCHEMY_DATABASE_URL,
    pool_pre_ping=True
)
SessionLocal = sessionmaker(autocommit=False, autoflush=False, bind=engine)
Base = declarative_base()

# Dependency to get DB session
def get_db():
    db = SessionLocal()
    try:
        yield db
    finally:
        db.close()

# --- SQLAlchemy Models (FastAPI's view of Laravel's tables) ---
class AdModel(Base):
    __tablename__ = "ads"
    id = Column(Integer, primary_key=True, index=True)
    tenant_id = Column(Integer, index=True)
    name = Column(String)
    content = Column(Text)
    start_time = Column(DateTime)
    end_time = Column(DateTime)
    target_audience = Column(JSON, nullable=True) # Assuming JSON support
    created_at = Column(DateTime, default=datetime.now)
    updated_at = Column(DateTime, default=datetime.now, onupdate=datetime.now)

class EventModel(Base):
    __tablename__ = "events"
    id = Column(Integer, primary_key=True, index=True)
    tenant_id = Column(Integer, index=True)
    ad_id = Column(Integer, index=True)
    user_id = Column(Integer, nullable=True, index=True)
    event_type = Column(String)
    data = Column(JSON, nullable=True)
    occurred_at = Column(TIMESTAMP, default=datetime.now)
    created_at = Column(DateTime, default=datetime.now)
    updated_at = Column(DateTime, default=datetime.now, onupdate=datetime.now)

# Attempt to create tables (for development/testing, migrations handle this in production Laravel)
# Base.metadata.create_all(bind=engine) # This should be handled by Laravel migrations. Keep commented for now.

# --- Configuration for Kafka & Redis ---
KAFKA_BROKER = os.getenv('KAFKA_BROKER', 'kafka:9092')
REDIS_HOST = os.getenv('REDIS_HOST', 'redis')

# --- Clients ---
kafka_producer = None
try:
    kafka_producer = KafkaProducer(
        bootstrap_servers=KAFKA_BROKER.split(','), # Split brokers for multiple nodes
        value_serializer=lambda v: json.dumps(v).encode('utf-8'),
        api_version=(0, 10, 1) # Specify API version to avoid common connection issues
    )
    # Test if Kafka is reachable by trying to list topics (non-blocking)
    kafka_producer.bootstrap_connected()
    print("Kafka producer initialized successfully and connected to broker.")
except Exception as e:
    print(f"Could not connect to Kafka at {KAFKA_BROKER}: {e}. Event logging to Kafka will be disabled.", file=sys.stderr)
    kafka_producer = None # Ensure it's None if connection fails
    
redis_client = None
try:
    redis_client = redis.StrictRedis(host=REDIS_HOST, port=6379, db=0, socket_connect_timeout=1) # Short timeout
    redis_client.ping() # Test connection
    print("Redis client initialized successfully and connected.")
except Exception as e:
    print(f"Could not connect to Redis at {REDIS_HOST}: {e}. Event logging to Redis will be disabled.", file=sys.stderr)
    redis_client = None # Ensure it's None if connection fails

# --- Recommendation Logic (Enhanced Collaborative Filtering with DB Data) ---
def enhanced_collaborative_filtering(user_id: int, db: Session, top_n: int = 5) -> List[Dict[str, Any]]:
    """
    Enhanced item-based collaborative filtering simulation, considering:
    1. User's interacted ads (from MySQL events table).
    2. Simulated recency and frequency (via occurred_at and event_type).
    3. Similarity between ads (based on shared tags - fetched from DB ads).
    4. Ad click-through rates (CTR) for a general popularity boost.
    """
    try:
        ads_from_db = db.query(AdModel).all()
        all_ads_data = {ad.id: {"id": ad.id, "name": ad.name, "tags": ad.target_audience.get("interests", []) if ad.target_audience else []} for ad in ads_from_db}

        # Calculate general ad CTRs
        ad_impressions = {}
        ad_clicks = {}
        all_events = db.query(EventModel).all() # Fetch all events to calculate global CTR
        for event in all_events:
            if event.event_type == 'impression':
                ad_impressions[event.ad_id] = ad_impressions.get(event.ad_id, 0) + 1
            elif event.event_type == 'click':
                ad_clicks[event.ad_id] = ad_clicks.get(event.ad_id, 0) + 1
        
        ad_ctrs = {ad_id: ad_clicks.get(ad_id, 0) / ad_impressions[ad_id] if ad_impressions.get(ad_id, 0) > 0 else 0 for ad_id in ad_impressions}

        # Fetch user's event history from the database
        user_events = db.query(EventModel).filter(EventModel.user_id == user_id).order_by(EventModel.occurred_at.desc()).all()
        
        user_interactions = []
        for event in user_events:
            # Assign weights based on event type and recency
            weight = 1.0 if event.event_type == 'click' else 0.5 # Clicks are weighted higher
            
            # Recency factor: more recent events have higher impact
            time_diff_seconds = (datetime.now() - event.occurred_at).total_seconds()
            # Normalize recency over a period (e.g., 60 days)
            recency_factor = max(0.1, 1 - (time_diff_seconds / (86400 * 60))) # Max 60 days relevance
            
            user_interactions.append({"ad_id": event.ad_id, "timestamp": event.occurred_at.timestamp(), "weight": weight * recency_factor})

        if not user_interactions:
            print(f"User {user_id} has no history. Falling back to random recommendation with CTR bias.")
            # If no history, recommend based on general popularity (CTR)
            sorted_by_ctr = sorted(all_ads_data.values(), key=lambda ad: ad_ctrs.get(ad['id'], 0), reverse=True)
            return random.sample(sorted_by_ctr, min(top_n, len(sorted_by_ctr)))

        tag_scores: Dict[str, float] = {}
        interacted_ad_ids = set()
        for interaction in user_interactions:
            ad_id = interaction["ad_id"]
            interacted_ad_ids.add(ad_id)
            ad_info = all_ads_data.get(ad_id)
            if ad_info and "tags" in ad_info:
                weight = interaction.get("weight", 1.0)
                for tag in ad_info["tags"]:
                    tag_scores[tag] = tag_scores.get(tag, 0.0) + weight

        total_score = sum(tag_scores.values())
        if total_score > 0:
            for tag in tag_scores:
                tag_scores[tag] /= total_score # Normalize tag scores

        ad_scores: Dict[int, float] = {}
        for ad_id, ad_info in all_ads_data.items():
            if ad_id in interacted_ad_ids:
                continue # Do not recommend ads the user has already interacted with recently

            current_ad_score = 0.0
            if "tags" in ad_info:
                for tag in ad_info["tags"]:
                    current_ad_score += tag_scores.get(tag, 0.0) # Add score from collaborative filtering

            # Add a component for general popularity (CTR), scaled down to not overpower personalization
            current_ad_score += ad_ctrs.get(ad_id, 0) * 0.5 # CTR has a weight of 0.5

            ad_scores[ad_id] = current_ad_score
        
        sorted_ads = sorted(ad_scores.items(), key=lambda item: item[1], reverse=True)
        
        recommendations = []
        for ad_id, score in sorted_ads:
            if score > 0: # Only recommend ads with a positive score
                recommendations.append(all_ads_data[ad_id])
                if len(recommendations) >= top_n:
                    break
        
        # If not enough recommendations from collaborative filtering, fill with top CTR ads (excluding already recommended)
        if len(recommendations) < top_n:
            fill_count = top_n - len(recommendations)
            current_recommended_ids = {ad['id'] for ad in recommendations}
            
            # Get all ads sorted by CTR, filter out already recommended ones
            all_ads_sorted_by_ctr = sorted(all_ads_data.values(), key=lambda ad: ad_ctrs.get(ad['id'], 0), reverse=True)
            available_ads_for_fill = [ad for ad in all_ads_sorted_by_ctr if ad['id'] not in current_recommended_ids]

            if available_ads_for_fill:
                random_fill = random.sample(available_ads_for_fill, min(fill_count, len(available_ads_for_fill)))
                recommendations.extend(random_fill)

        return recommendations
    except Exception as e:
        print(f"Database query failed during recommendation: {e}", file=sys.stderr)
        # Fallback to random recommendations if DB fails
        if 'all_ads_data' in locals() and all_ads_data:
            print("Falling back to random recommendations due to DB error.")
            return random.sample(list(all_ads_data.values()), min(top_n, len(all_ads_data)))
        else:
            print("No ads available for recommendation even with fallback.")
            return [] # No ads available for recommendation


# --- Pydantic Models ---
class Event(BaseModel):
    user_id: int
    ad_id: int
    event_type: str  # 'click', 'impression'
    tenant_id: int
    timestamp: Optional[int] = None

# --- FastAPI App ---
app = FastAPI(
    title="Advertisement Recommendation and Event Logging API",
    description="Provides ad recommendations and records user events.",
    version="1.0.0",
    docs_url="/docs",
    redoc_url="/redoc"
)

@app.get("/recommend", summary="Get ad recommendations for a user")
def get_recommendations(user_id: int, db: Session = Depends(get_db)):
    """
    Retrieve personalized ad recommendations for a given user based on enhanced collaborative filtering.
    Data is fetched from the MySQL database.
    """
    recommendations = enhanced_collaborative_filtering(user_id, db)
    return {"user_id": user_id, "recommendations": recommendations}

@app.post("/log-event", summary="Log a user event (click or impression)")
def log_event(event: Event):
    """
    Logs a user event to a message queue (Kafka) or a fallback storage (Redis or file).
    """
    if event.timestamp is None:
        event.timestamp = int(time.time())
    
    event_data = event.dict()
    
    # Try pushing to Kafka first
    if kafka_producer and kafka_producer.bootstrap_connected():
        try:
            future = kafka_producer.send('ad_events', event_data)
            future.get(timeout=5) # Block until the message is sent, short timeout
            print(f"Event pushed to Kafka topic 'ad_events': {event_data}")
            return {"message": "Event logged to Kafka successfully", "event": event_data}
        except Exception as e:
            print(f"Failed to push to Kafka: {e}. Falling back to Redis.", file=sys.stderr)
    else:
        print("Kafka producer not connected or not initialized. Falling back to Redis.", file=sys.stderr)

    # Fallback to Redis
    if redis_client:
        try:
            redis_client.rpush('event_queue', json.dumps(event_data))
            print(f"Event pushed to Redis (fallback): {event_data}")
            return {"message": "Event logged to Redis (fallback) successfully", "event": event_data}
        except Exception as redis_e:
            print(f"Failed to push to Redis as well: {redis_e}. Final fallback: writing to file.", file=sys.stderr)
            # Final fallback: log to a file
            with open("event_log_fallback.txt", "a") as f:
                f.write(json.dumps(event_data) + "\n")
            raise HTTPException(status_code=500, detail="Failed to log event to Redis; logged to file instead.")
    
    # If neither is available and file logging also failed (unlikely for open("a")), or if file logging isn't desired for hard failure
    with open("event_log_fallback.txt", "a") as f:
        f.write(json.dumps(event_data) + "\n")
    raise HTTPException(status_code=503, detail="Event logging service is unavailable (Kafka/Redis/File fallback attempted).")

@app.get("/health")
def health_check():
    kafka_status = "connected"
    if not kafka_producer or not kafka_producer.bootstrap_connected():
        kafka_status = "disconnected"
        
    redis_status = "connected"
    if not redis_client:
        redis_status = "disconnected"
    else:
        try:
            redis_client.ping()
        except Exception:
            redis_status = "disconnected"
            
    return {"status": "ok", "kafka_status": kafka_status, "redis_status": redis_status}
