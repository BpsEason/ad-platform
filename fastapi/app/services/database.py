# FastAPI app/services/database.py
from sqlalchemy import create_engine
from sqlalchemy.ext.declarative import declarative_base
from sqlalchemy.orm import sessionmaker
import os

# Database connection details from environment variables
# In a real app, ensure these are robustly managed (e.g., Docker secrets)
DB_HOST = os.getenv("DB_HOST", "db")
DB_PORT = os.getenv("DB_PORT", "3306")
DB_DATABASE = os.getenv("DB_DATABASE", "ad_platform_db")
DB_USERNAME = os.getenv("DB_USERNAME", "user")
DB_PASSWORD = os.getenv("DB_PASSWORD", "password")

# SQLAlchemy Database URL
# For MySQL, use mysql+pymysql:// or mysql+mysqlconnector://
SQLALCHEMY_DATABASE_URL = f"mysql+pymysql://{DB_USERNAME}:{DB_PASSWORD}@{DB_HOST}:{DB_PORT}/{DB_DATABASE}"

# Create the SQLAlchemy engine
engine = create_engine(
    SQLALCHEMY_DATABASE_URL, 
    pool_pre_ping=True # Ensures connections are alive
)

# Create a SessionLocal class
# Each instance of SessionLocal will be a database session.
# The `autocommit=False` and `autoflush=False` are standard for web applications.
SessionLocal = sessionmaker(autocommit=False, autoflush=False, bind=engine)

# Base class for declarative models
Base = declarative_base()

# Dependency to get DB session
# This is a common pattern in FastAPI to manage database sessions per request.
def get_db():
    db = SessionLocal()
    try:
        yield db
    finally:
        db.close()
