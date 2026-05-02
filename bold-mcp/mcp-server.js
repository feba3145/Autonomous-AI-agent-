#!/usr/bin/env node
/**
 * Magento MCP Server
 * Tools: product stock, search, product by SKU/ID, categories,
 *        related products, attributes, customer orders,
 *        shipment tracking
 */

const https = require("https");
const http  = require("http");
const readline = require("readline");

// ── helpers ────────────────────────────────────────────────────────────────

const BASE_URL  = (process.env.MAGENTO_BASE_URL || "").replace(/\/$/, "");
const API_TOKEN = process.env.MAGENTO_API_TOKEN  || "";

function magentoRequest(method, path, body = null) {
  return new Promise((resolve, reject) => {
    const url    = new URL(BASE_URL + "/rest/V1" + path);
    const isHttps = url.protocol === "https:";
    const lib    = isHttps ? https : http;

    const options = {
      hostname: url.hostname,
      port:     url.port || (isHttps ? 443 : 80),
      path:     url.pathname + url.search,
      method,
      headers: {
        "Authorization": `Bearer ${API_TOKEN}`,
        "Content-Type":  "application/json",
        "Accept":        "application/json",
      },
      rejectUnauthorized: false,   // self-signed dev certs
    };

    const req = lib.request(options, (res) => {
      let data = "";
      res.on("data", (chunk) => (data += chunk));
      res.on("end", () => {
        try { resolve(JSON.parse(data)); }
        catch { resolve({ raw: data }); }
      });
    });

    req.on("error", reject);
    if (body) req.write(JSON.stringify(body));
    req.end();
  });
}

// ── tool definitions ───────────────────────────────────────────────────────

const TOOLS = [
  {
    name: "get_product_stock",
    description: "Get stock/inventory information for a product by SKU",
    inputSchema: {
      type: "object",
      properties: { sku: { type: "string", description: "Product SKU" } },
      required: ["sku"],
    },
  },
  {
    name: "search_products",
    description: "Search products by keyword",
    inputSchema: {
      type: "object",
      properties: {
        query:     { type: "string",  description: "Search query" },
        page_size: { type: "integer", description: "Results per page (default 5)", default: 5 },
      },
      required: ["query"],
    },
  },
  {
    name: "get_product_by_sku",
    description: "Get full product details by SKU",
    inputSchema: {
      type: "object",
      properties: { sku: { type: "string" } },
      required: ["sku"],
    },
  },
  {
    name: "get_product_by_id",
    description: "Get product details by numeric product ID",
    inputSchema: {
      type: "object",
      properties: { id: { type: "integer" } },
      required: ["id"],
    },
  },
  {
    name: "get_product_categories",
    description: "Get categories assigned to a product",
    inputSchema: {
      type: "object",
      properties: { sku: { type: "string" } },
      required: ["sku"],
    },
  },
  {
    name: "get_related_products",
    description: "Get related/cross-sell/up-sell products for a SKU",
    inputSchema: {
      type: "object",
      properties: { sku: { type: "string" } },
      required: ["sku"],
    },
  },
  {
    name: "get_product_attributes",
    description: "Get all custom attributes of a product by SKU",
    inputSchema: {
      type: "object",
      properties: { sku: { type: "string" } },
      required: ["sku"],
    },
  },
  {
    name: "get_customer_ordered_products_by_email",
    description: "Get all orders placed by a customer email",
    inputSchema: {
      type: "object",
      properties: { email: { type: "string" } },
      required: ["email"],
    },
  },
  {
    name: "update_product_attribute",
    description: "Update a single attribute value on a product",
    inputSchema: {
      type: "object",
      properties: {
        sku:            { type: "string" },
        attribute_code: { type: "string" },
        value:          { type: "string" },
      },
      required: ["sku", "attribute_code", "value"],
    },
  },
  // ── NEW: shipment tracking
  {
    name: "get_tracking_info",
    description:
      "Get shipment tracking details for an order using its increment ID (e.g. 000000123). " +
      "Returns carrier, tracking number, status, and shipment date.",
    inputSchema: {
      type: "object",
      properties: {
        order_increment_id: {
          type: "string",
          description: "Magento order increment ID, e.g. 000000123",
        },
      },
      required: ["order_increment_id"],
    },
  },
  {
    name: "get_shipments_by_order_id",
    description: "Get all shipments for an order by internal order_id (integer)",
    inputSchema: {
      type: "object",
      properties: { order_id: { type: "integer" } },
      required: ["order_id"],
    },
  },
  {
    name: "get_order_by_increment_id",
    description: "Fetch full order details using the customer-facing increment ID",
    inputSchema: {
      type: "object",
      properties: {
        order_increment_id: { type: "string" },
      },
      required: ["order_increment_id"],
    },
  },
  ,{
    name: "create_shipment",
    description: "Create a shipment for an order and assign tracking number",
    inputSchema: {
      type: "object",
      properties: {
        order_id: { type: "integer" },
        carrier_code: { type: "string" },
        carrier_title: { type: "string" },
        tracking_number: { type: "string" },
        notify: { type: "boolean" }
      },
      required: ["order_id", "carrier_code", "carrier_title", "tracking_number"]
    }
  }
  ,{
    name: "cancel_order",
    description: "Cancel a Magento order by order ID",
    inputSchema: { type: "object", properties: { order_id: { type: "integer" } }, required: ["order_id"] }
  }
  ,{
    name: "create_creditmemo",
    description: "Create a refund credit memo for an order",
    inputSchema: { type: "object", properties: { order_id: { type: "integer" } }, required: ["order_id"] }
  }
  ,{
    name: "submit_review",
    description: "Submit a product review",
    inputSchema: { type: "object", properties: { product_id: { type: "integer" }, rating: { type: "integer" }, review_text: { type: "string" }, nickname: { type: "string" } }, required: ["product_id", "rating", "review_text", "nickname"] }
  }
];

// ── tool handlers ──────────────────────────────────────────────────────────

async function handleTool(name, args) {
  switch (name) {

    case "get_product_stock": {
      const sku = encodeURIComponent(args.sku);
      return magentoRequest("GET", `/stockItems/${sku}`);
    }

    case "search_products": {
      const n   = args.page_size || 5;
      const q   = encodeURIComponent(args.query);
      const path =
        `/products?searchCriteria[filter_groups][0][filters][0][field]=name` +
        `&searchCriteria[filter_groups][0][filters][0][value]=%25${q}%25` +
        `&searchCriteria[filter_groups][0][filters][0][condition_type]=like` +
        `&searchCriteria[pageSize]=${n}`;
      return magentoRequest("GET", path);
    }

    case "get_product_by_sku": {
      const sku = encodeURIComponent(args.sku);
      return magentoRequest("GET", `/products/${sku}`);
    }

    case "get_product_by_id": {
      const path =
        `/products?searchCriteria[filter_groups][0][filters][0][field]=entity_id` +
        `&searchCriteria[filter_groups][0][filters][0][value]=${args.id}` +
        `&searchCriteria[filter_groups][0][filters][0][condition_type]=eq`;
      const res = await magentoRequest("GET", path);
      return res.items ? res.items[0] : res;
    }

    case "get_product_categories": {
      const sku = encodeURIComponent(args.sku);
      return magentoRequest("GET", `/products/${sku}/links/associated`);
    }

    case "get_related_products": {
      const sku = encodeURIComponent(args.sku);
      return magentoRequest("GET", `/products/${sku}/links/related`);
    }

    case "get_product_attributes": {
      const sku = encodeURIComponent(args.sku);
      const product = await magentoRequest("GET", `/products/${sku}`);
      return product.custom_attributes || [];
    }

    case "get_customer_ordered_products_by_email": {
      const email = encodeURIComponent(args.email);
      const path =
        `/orders?searchCriteria[filter_groups][0][filters][0][field]=customer_email` +
        `&searchCriteria[filter_groups][0][filters][0][value]=${email}` +
        `&searchCriteria[filter_groups][0][filters][0][condition_type]=eq`;
      return magentoRequest("GET", path);
    }

    case "update_product_attribute": {
      const sku  = encodeURIComponent(args.sku);
      const body = {
        product: {
          custom_attributes: [
            { attribute_code: args.attribute_code, value: args.value },
          ],
        },
      };
      return magentoRequest("PUT", `/products/${sku}`, body);
    }

    // ── shipment tracking ────────────────────────────────────────────────

    case "get_order_by_increment_id": {
      const path =
        `/orders?searchCriteria[filter_groups][0][filters][0][field]=increment_id` +
        `&searchCriteria[filter_groups][0][filters][0][value]=${encodeURIComponent(args.order_increment_id)}` +
        `&searchCriteria[filter_groups][0][filters][0][condition_type]=eq`;
      const res = await magentoRequest("GET", path);
      if (res.items && res.items.length > 0) return res.items[0];
      return { error: "Order not found", increment_id: args.order_increment_id };
    }

    case "get_tracking_info": {
      // Step 1: resolve order_id from increment_id
      const orderPath =
        `/orders?searchCriteria[filter_groups][0][filters][0][field]=increment_id` +
        `&searchCriteria[filter_groups][0][filters][0][value]=${encodeURIComponent(args.order_increment_id)}` +
        `&searchCriteria[filter_groups][0][filters][0][condition_type]=eq`;
      const orderRes = await magentoRequest("GET", orderPath);
      const order    = orderRes.items && orderRes.items[0];
      if (!order) return { error: "Order not found", order_increment_id: args.order_increment_id };

      // Step 2: fetch shipments for this order
      const shipPath =
        `/shipments?searchCriteria[filter_groups][0][filters][0][field]=order_id` +
        `&searchCriteria[filter_groups][0][filters][0][value]=${order.entity_id}` +
        `&searchCriteria[filter_groups][0][filters][0][condition_type]=eq`;
      const shipRes = await magentoRequest("GET", shipPath);

      if (!shipRes.items || shipRes.items.length === 0) {
        return {
          order_increment_id: args.order_increment_id,
          order_status:       order.status,
          message:            "No shipment created yet for this order.",
        };
      }

      // Flatten all tracks across shipments
      const tracks = shipRes.items.flatMap((s) =>
        (s.tracks || []).map((t) => ({
          shipment_id:    s.entity_id,
          shipment_date:  s.created_at,
          carrier_code:   t.carrier_code,
          carrier_title:  t.title,
          tracking_number: t.track_number,
        }))
      );

      return {
        order_increment_id: args.order_increment_id,
        order_id:           order.entity_id,
        order_status:       order.status,
        shipments_count:    shipRes.items.length,
        tracks,
      };
    }

    case "get_shipments_by_order_id": {
      const path =
        `/shipments?searchCriteria[filter_groups][0][filters][0][field]=order_id` +
        `&searchCriteria[filter_groups][0][filters][0][value]=${args.order_id}` +
        `&searchCriteria[filter_groups][0][filters][0][condition_type]=eq`;
      return magentoRequest("GET", path);
    }

    case "create_shipment": {
      const shipBody = {
        items: [],
        notify: args.notify !== false,
        tracks: [{
          carrier_code: args.carrier_code,
          title: args.carrier_title,
          track_number: args.tracking_number
        }]
      };
      return magentoRequest("POST", `/order/${args.order_id}/ship`, shipBody);
    }
    case "cancel_order": {
      return magentoRequest("POST", `/orders/${args.order_id}/cancel`);
    }
    default:
    case "create_creditmemo": {
      return magentoRequest("POST", `/order/${args.order_id}/refund`, { items: [], notify: true });
    }
      throw new Error(`Unknown tool: ${name}`);
    case "submit_review": {
      const body = {
        data: {
          title: "Customer Review",
          detail: args.review_text,
          nickname: args.nickname,
          ratings: [{ rating_name: "Rating", percent: args.rating * 20 }]
        },
        entity_pk_value: args.product_id,
        entity_code: "product"
      };
      return magentoRequest("POST", "/reviews", body);
    }
  }
}

// ── JSON-RPC stdio server ──────────────────────────────────────────────────

const rl = readline.createInterface({ input: process.stdin });

rl.on("line", async (line) => {
  let req;
  try { req = JSON.parse(line.trim()); }
  catch { return; }

  const { id, method, params } = req;

  try {
    let result;

    if (method === "initialize") {
      result = {
        protocolVersion: "2024-11-05",
        capabilities:    { tools: {} },
        serverInfo:      { name: "magento-mcp", version: "2.0.0" },
      };
    } else if (method === "tools/list") {
      result = { tools: TOOLS };
    } else if (method === "tools/call") {
      const toolName = params.name;
      const toolArgs = params.arguments || {};
      const data     = await handleTool(toolName, toolArgs);
      result = {
        content: [{ type: "text", text: JSON.stringify(data, null, 2) }],
      };
    } else {
      throw new Error(`Method not supported: ${method}`);
    }

    process.stdout.write(
      JSON.stringify({ jsonrpc: "2.0", id, result }) + "\n"
    );
  } catch (err) {
    process.stdout.write(
      JSON.stringify({
        jsonrpc: "2.0",
        id,
        error: { code: -32000, message: err.message },
      }) + "\n"
    );
  }
});
