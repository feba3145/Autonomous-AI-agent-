# Autonomous AI E-Commerce Agent

An AI-powered shopping assistant for Magento that autonomously handles product discovery, cart management, checkout, and order placement using natural language.

## Stack
- **Magento 2** — E-commerce platform (Luma sample data)
- **FastAPI** — AI backend API (port 8002)
- **pgvector + PostgreSQL** — Vector similarity search for products
- **Ollama (llama3.2)** — Local LLM inference
- **sentence-transformers** — Product embeddings (all-MiniLM-L6-v2)
- **Bold Commerce MCP** — Autonomous checkout automation (coming soon)

## Features (Current)
- RAG-powered product recommendations
- Conversational memory (session history)
- Similarity threshold (no irrelevant results)
- Price filter (no $0 parent products)

## Features (Roadmap)
- Guest cart with temporary session
- Autonomous checkout (add to cart → address → COD → order)
- Home/office address memory per customer
- Bold Commerce MCP integration
- Frontend chat widget embedded in Magento

## Quick Start
See `fastapi-backend/` for the AI API.
