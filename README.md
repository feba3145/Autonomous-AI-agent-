# ShopAI — Autonomous AI-Powered E-Commerce Shopping Assistant

**ShopAI** (assistant name: **Aria**) is an autonomous, voice-and-text-enabled AI shopping assistant built for e-commerce platforms. It combines LLM-driven intent understanding, retrieval-augmented generation (RAG) over a product catalog, and real-time speech interaction to help customers search, filter, and purchase products conversationally.

This project is self-hosted on an Ubuntu Linux server and integrates with a Magento 2 storefront via its REST API.

---

## Features

- **Conversational shopping** — natural language product search, filtering (size, color, category, price), and recommendations
- **Voice interaction** — speech-to-text input and text-to-speech responses via Deepgram
- **RAG-powered retrieval** — semantic product search using vector embeddings and pgvector similarity search
- **LLM-driven intent detection** — no hardcoded keyword matching; intent classification and slot-filling handled by Groq-hosted LLaMA 3
- **Tool-calling architecture (MCP)** — structured tool invocation for actions like cart management, checkout, and coupon application
- **Cart & checkout flow** — add-to-cart with deduplication, coupon application, and order placement against Magento 2
- **Magento 2 integration** — live product data, inventory, and order management via Magento's REST API

---

## Architecture Overview

```
┌─────────────┐     ┌──────────────┐     ┌─────────────────┐
│  Frontend    │────▶│   FastAPI     │────▶│   Groq API        │
│ (Web/Voice)  │◀────│   Backend     │◀────│  (LLaMA 3)        │
└─────────────┘     └──────┬───────┘     └─────────────────┘
                            │
              ┌─────────────┼─────────────┐
              ▼             ▼             ▼
      ┌──────────────┐ ┌──────────┐ ┌──────────────┐
      │ PostgreSQL +  │ │ Deepgram │ │  Magento 2    │
      │  pgvector     │ │ TTS/STT  │ │  REST API     │
      │ (RAG store)   │ │          │ │ (products,     │
      └──────────────┘ └──────────┘ │  cart, orders) │
                                     └──────────────┘
```

### Request Flow

1. **Input** — User sends a text query or voice input (transcribed via Deepgram STT, model `nova-2`)
2. **Intent Detection** — Groq LLaMA 3 classifies intent (search, filter, add-to-cart, checkout, etc.)
3. **RAG Retrieval** — Query is embedded and matched against product embeddings in PostgreSQL/pgvector using cosine similarity
4. **Context Augmentation** — Retrieved products are injected into the LLM prompt as context
5. **Response Generation** — Groq LLaMA 3 generates a natural language response
6. **Tool Calling (MCP)** — If the intent requires an action (add to cart, apply coupon, checkout), the appropriate tool is invoked against the Magento 2 REST API
7. **Output** — Response is returned as text and/or converted to speech via Deepgram TTS (model `aura-asteria-en`)

---

## Tech Stack

| Layer | Technology |
|---|---|
| Backend | FastAPI (Python) |
| Database | PostgreSQL + pgvector |
| LLM | Groq API (LLaMA 3) |
| Speech-to-Text | Deepgram (`nova-2`) |
| Text-to-Speech | Deepgram (`aura-asteria-en`) |
| E-commerce Platform | Magento 2 (REST API) |
| Tool Orchestration | MCP (Model Context Protocol)-style tool-calling |
| Hosting | Self-hosted on Ubuntu Linux |

---

## Prerequisites

- Ubuntu Linux server
- Python 3.10+
- PostgreSQL 14+ with the `pgvector` extension enabled
- A running Magento 2 instance with REST API access
- API keys for:
  - Groq API
  - Deepgram




| Endpoint | Description |
|---|---|
| `POST /rag-chat` | Main conversational endpoint (text query → RAG response) |
| `POST /stt` | Speech-to-text transcription |
| `POST /tts` | Text-to-speech synthesis |
| `POST /cart/add` | Add item to cart |
| `POST /checkout` | Process checkout with coupon support |

---

## Project Structure

```
Autonomous-AI-agent-/
├── main.py                  # FastAPI app entry point
├── routers/
│   ├── rag_router.py        # RAG chat endpoint
│   ├── tts_router.py        # Deepgram TTS integration
│   ├── stt_router.py        # Deepgram STT integration
│   └── cart_router.py       # Cart & checkout logic
├── core/
│   ├── intent_detection.py  # LLM-driven intent classification
│   ├── embeddings.py        # Product embedding generation
│   ├── retrieval.py         # pgvector similarity search
│   └── mcp_tools.py         # Tool-calling definitions
├── scripts/
│   ├── setup_db.py
│   └── embed_products.py
├── requirements.txt
└── .env
```

---



This project was developed as part of an academic study on autonomous AI agents in e-commerce, evaluating the integration of LLM-based conversational interfaces, RAG-based product retrieval, and voice interaction within a real-world Magento 2 storefront.

---
