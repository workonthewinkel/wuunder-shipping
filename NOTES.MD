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