# FastAPI app/models/models.py
from sqlalchemy import Column, Integer, String, Text, DateTime, JSON, TIMESTAMP
from sqlalchemy.ext.declarative import declarative_base
from datetime import datetime

Base = declarative_base()

class AdModel(Base):
    __tablename__ = "ads"
    id = Column(Integer, primary_key=True, index=True)
    tenant_id = Column(Integer, index=True)
    name = Column(String)
    content = Column(Text)
    start_time = Column(DateTime)
    end_time = Column(DateTime)
    target_audience = Column(JSON, nullable=True)
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
