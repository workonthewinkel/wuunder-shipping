## Admin Flow
1. Install wuunder plugin
2. Go to Woocommerce -> Settings -> Tab “Wuunder”
3. Wuunder shows intro screen + two tabs
    3a. Settings 
        - Field with API Key
    3b. Carriers
        - Shows a list with shipping methods which can be enabled/disabled
        - If no entries, automatically retrieve methods
        - If entries, show option to refresh methods
        - Save button = save as shipping methods in woocomerce (important: shipping method should contain wuunder_shipping_method_id)
    3c. This integration can be tested by checking available shipping methods in Woocommerce -> Settings -> Shopping -> Rest of world

## Customer Flow
1. Adds product to cart
2. Goes to checkout 
3. Customer can select ANY shipping method that is available
4. Shipping method is saved to order (important: contains wuunder_shipping_method_id)


Wuunder API lives on: https://api.wearewuunder.com
Wuunder API Docs: https://api.wearewuunder.com/api/docs/


### Retrieving carriers
https://my.wearewuunder.com/api/docs/api/v2/operations/api_v2_carriers_index.html

#### Returned Example JSON
```
{
  "carriers": [
    {
      "carrier_products": [
        {
          "code": "POSTNL_DELIVERY",
          "description": "PostNL - Delivery",
          "name": "Delivery",
          "preferred_service_level": "post_nl:POSTNL_DELIVERY"
        }
      ],
      "code": "POST_NL",
      "image_url": "https://www.example.com/test.jpeg",
      "name": "PostNL"
    }
  ]
}
```


### The data the REST API currently (14/08/2025) returns:
`curl -X GET "https://wuundershop.c7/wp-json/wc/v3/orders" -u "ck_c83e58bfd7429c2b68ff225f87781a54a1312826:cs_611658bcc7928109bf87cf974b40d1ea79db9697"`
```
[
  {
    "id": 71,
    "parent_id": 0,
    "status": "on-hold",
    "currency": "EUR",
    "version": "10.0.4",
    "prices_include_tax": false,
    "date_created": "2025-08-14T15:59:39",
    "date_modified": "2025-08-14T15:59:39",
    "discount_total": "0.00",
    "discount_tax": "0.00",
    "shipping_total": "10.00",
    "shipping_tax": "0.00",
    "cart_tax": "0.00",
    "total": "23.69",
    "total_tax": "0.00",
    "customer_id": 1,
    "order_key": "wc_order_FvdOkVR79PhMC",
    "billing": {
      "first_name": "test classic checkout",
      "last_name": "test",
      "company": "",
      "address_1": "test 34",
      "address_2": "",
      "city": "efsetset",
      "state": "",
      "postcode": "3454 EF",
      "country": "NL",
      "email": "admin@wuundershop.test",
      "phone": ""
    },
    "shipping": {
      "first_name": "test classic checkout",
      "last_name": "test",
      "company": "",
      "address_1": "test 34",
      "address_2": "",
      "city": "efsetset",
      "state": "",
      "postcode": "3454 EF",
      "country": "NL",
      "phone": ""
    },
    "payment_method": "bacs",
    "payment_method_title": "Direct bank transfer",
    "transaction_id": "",
    "customer_ip_address": "100.67.245.4",
    "customer_user_agent": "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36",
    "created_via": "checkout",
    "customer_note": "",
    "date_completed": null,
    "date_paid": null,
    "cart_hash": "2566baa3788a0f2da5cff04a1cbdd0e3",
    "number": "71",
    "meta_data": [
      {
        "id": 242,
        "key": "is_vat_exempt",
        "value": "no"
      },
      {
        "id": 245,
        "key": "_wc_order_attribution_source_type",
        "value": "typein"
      },
      {
        "id": 246,
        "key": "_wc_order_attribution_referrer",
        "value": "https://wuundershop.c7/wp-admin/post.php?post=32&action=edit"
      },
      {
        "id": 247,
        "key": "_wc_order_attribution_utm_source",
        "value": "(direct)"
      },
      {
        "id": 248,
        "key": "_wc_order_attribution_session_entry",
        "value": "https://wuundershop.c7/"
      },
      {
        "id": 249,
        "key": "_wc_order_attribution_session_start_time",
        "value": "2025-08-11 08:59:55"
      },
      {
        "id": 250,
        "key": "_wc_order_attribution_session_pages",
        "value": "6"
      },
      {
        "id": 251,
        "key": "_wc_order_attribution_session_count",
        "value": "7"
      },
      {
        "id": 252,
        "key": "_wc_order_attribution_user_agent",
        "value": "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36"
      },
      {
        "id": 253,
        "key": "_wc_order_attribution_device_type",
        "value": "Desktop"
      },
      {
        "id": 254,
        "key": "_wuunder_shipping_method_id",
        "value": "ANY:CHEAPEST"
      }
    ],
    "line_items": [
      {
        "id": 212,
        "name": "Durable Granite Car - Black",
        "product_id": 41,
        "variation_id": 42,
        "quantity": 1,
        "tax_class": "",
        "subtotal": "13.69",
        "subtotal_tax": "0.00",
        "total": "13.69",
        "total_tax": "0.00",
        "taxes": [],
        "meta_data": [
          {
            "id": 1774,
            "key": "pa_color",
            "value": "black",
            "display_key": "Color",
            "display_value": "Black"
          },
          {
            "id": 1782,
            "key": "_reduced_stock",
            "value": "1",
            "display_key": "_reduced_stock",
            "display_value": "1"
          }
        ],
        "sku": "durable-granite-car-45989995",
        "price": 13.69,
        "image": {
          "id": 15,
          "src": "https://wuundershop.c7/wp-content/uploads/2025/08/img-eos.png"
        },
        "parent_name": "Durable Granite Car"
      }
    ],
    "tax_lines": [],
    "shipping_lines": [
      {
        "id": 213,
        "method_title": "Any available carrier - Any cheapest",
        "method_id": "wuunder_shipping",
        "instance_id": "46",
        "total": "10.00",
        "total_tax": "0.00",
        "taxes": [],
        "tax_status": "taxable",
        "meta_data": [
          {
            "id": 1780,
            "key": "wuunder_method_id",
            "value": "ANY:CHEAPEST",
            "display_key": "wuunder_method_id",
            "display_value": "ANY:CHEAPEST"
          },
          {
            "id": 1781,
            "key": "wuunder_carrier_data",
            "value": {
              "carrier_name": "Any available carrier",
              "product_name": "Any cheapest",
              "service_level": "any:cheapest"
            },
            "display_key": "wuunder_carrier_data",
            "display_value": {
              "carrier_name": "Any available carrier",
              "product_name": "Any cheapest",
              "service_level": "any:cheapest"
            }
          }
        ]
      }
    ],
    "fee_lines": [],
    "coupon_lines": [],
    "refunds": [],
    "payment_url": "https://wuundershop.c7/checkout/order-pay/71/?pay_for_order=true&key=wc_order_FvdOkVR79PhMC",
    "is_editable": true,
    "needs_payment": false,
    "needs_processing": true,
    "date_created_gmt": "2025-08-14T15:59:39",
    "date_modified_gmt": "2025-08-14T15:59:39",
    "date_completed_gmt": null,
    "date_paid_gmt": null,
    "currency_symbol": "€",
    "_links": {
      "self": [
        {
          "href": "https://wuundershop.c7/wp-json/wc/v3/orders/71",
          "targetHints": {
            "allow": [
              "GET",
              "POST",
              "PUT",
              "PATCH",
              "DELETE"
            ]
          }
        }
      ],
      "collection": [
        {
          "href": "https://wuundershop.c7/wp-json/wc/v3/orders"
        }
      ],
      "email_templates": [
        {
          "embeddable": true,
          "href": "https://wuundershop.c7/wp-json/wc/v3/orders/71/actions/email_templates"
        }
      ],
      "customer": [
        {
          "href": "https://wuundershop.c7/wp-json/wc/v3/customers/1"
        }
      ]
    }
  }
]
```