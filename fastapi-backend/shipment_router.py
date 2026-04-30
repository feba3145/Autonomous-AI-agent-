"""
fastapi-backend/shipment_router.py
-----------------------------------
Shipment tracking + store locator endpoints.

Mount in main.py with:
    from shipment_router import router as shipment_router
    app.include_router(shipment_router, prefix="/shipment", tags=["Shipment"])
"""

from fastapi import APIRouter, HTTPException
from pydantic import BaseModel
from mcp_client import mcp   # uses the shared singleton from mcp_client.py

router = APIRouter()


# ─────────────────────────────────────────────────────────────────────────────
# Request / Response models
# ─────────────────────────────────────────────────────────────────────────────

class TrackRequest(BaseModel):
    """Body for the chat-UI tracking POST."""
    order_increment_id: str


class TrackResponse(BaseModel):
    order_increment_id: str
    order_id: int | None = None
    order_status: str | None = None
    shipments_count: int | None = None
    tracks: list[dict] = []
    message: str | None = None
    error: str | None = None


# ─────────────────────────────────────────────────────────────────────────────
# Endpoints
# ─────────────────────────────────────────────────────────────────────────────

@router.get(
    "/track/{order_increment_id}",
    summary="Track shipment by order increment ID",
    description=(
        "Main tracking endpoint. Pass the customer-facing order number "
        "(e.g. 000000123) and get back carrier name, tracking number(s), "
        "and current order status."
    ),
    response_model=TrackResponse,
)
async def track_by_increment_id(order_increment_id: str):
    """
    GET /shipment/track/000000123
    """
    result = mcp.get_tracking_info(order_increment_id)
    if not result:
        raise HTTPException(status_code=404, detail="No tracking data returned from Magento.")
    if result.get("error"):
        raise HTTPException(status_code=404, detail=result["error"])
    return result


@router.post(
    "/track",
    summary="Track shipment (chat UI / POST variant)",
    description="Same as GET /track/{id} but accepts a JSON body — easier to call from a chat widget.",
    response_model=TrackResponse,
)
async def track_post(body: TrackRequest):
    """
    POST /shipment/track
    Body: { "order_increment_id": "000000123" }
    """
    result = mcp.get_tracking_info(body.order_increment_id)
    if not result:
        raise HTTPException(status_code=404, detail="No tracking data returned from Magento.")
    if result.get("error"):
        raise HTTPException(status_code=404, detail=result["error"])
    return result


@router.get(
    "/order/{order_increment_id}",
    summary="Get full order by increment ID",
    description="Returns the complete Magento order object for a given increment ID.",
)
async def get_order(order_increment_id: str):
    """
    GET /shipment/order/000000123
    """
    result = mcp.get_order_by_increment_id(order_increment_id)
    if not result or result.get("error"):
        detail = (result or {}).get("error", "Order not found.")
        raise HTTPException(status_code=404, detail=detail)
    return result


@router.get(
    "/shipments/{order_id}",
    summary="Get all shipments for an order (by internal ID)",
    description=(
        "Returns every shipment record linked to the internal numeric order_id, "
        "including all tracks per shipment."
    ),
)
async def get_shipments(order_id: int):
    """
    GET /shipment/shipments/42
    """
    result = mcp.get_shipments_by_order_id(order_id)
    if not result:
        raise HTTPException(status_code=404, detail="No shipments found for this order ID.")
    return result
