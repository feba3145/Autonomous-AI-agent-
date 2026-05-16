from fastapi import APIRouter, HTTPException
from pydantic import BaseModel
from mcp_client import mcp

router = APIRouter()

class ShipmentCreate(BaseModel):
    order_id: int
    carrier_code: str = "custom"
    carrier_title: str = "Custom"
    tracking_number: str
    notify: bool = True

class TrackingAdd(BaseModel):
    shipment_id: int
    carrier_code: str = "custom"
    carrier_title: str = "Custom"
    tracking_number: str
@router.post("/admin/shipment/create")
def create_shipment(data: ShipmentCreate):
    result = mcp.create_shipment(
        data.order_id,
        data.carrier_code,
        data.carrier_title,
        data.tracking_number,
        data.notify
    )
    
    if not result:
        raise HTTPException(status_code=400, detail="Could not create shipment")
    if isinstance(result, dict) and result.get("message"):
        raise HTTPException(status_code=400, detail=result.get("message"))
    return {"message": "Shipment created successfully", "shipment_id": result}
@router.post("/admin/shipment/track/add")
def add_tracking(data: TrackingAdd):
    result = mcp.add_tracking(
        data.shipment_id,
        data.carrier_code,
        data.carrier_title,
        data.tracking_number
    )
    if not result or result.get("error"):
        raise HTTPException(status_code=400, detail=result.get("error", "Could not add tracking"))
    return {"message": "Tracking added", "track": result}

@router.get("/admin/shipment/{order_id}")
def get_shipment(order_id: int):
    result = mcp.get_shipments_by_order_id(order_id)
    if not result:
        raise HTTPException(status_code=404, detail="No shipments found")
    return result

