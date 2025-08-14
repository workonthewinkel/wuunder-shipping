### Delete all orders
`wp wc shop_order list --format=ids --user=1 | xargs -n1 wp wc shop_order delete --force=true --user=1`

### Retrieve latest orders
`curl -X GET "https://your-domain.com/wp-json/wc/v3/orders" -u "consumer_key:consumer_secret"`

#### mklasen env
`curl -X GET "https://wuundershop.c7/wp-json/wc/v3/orders" -u "ck_c83e58bfd7429c2b68ff225f87781a54a1312826:cs_611658bcc7928109bf87cf974b40d1ea79db9697"  | jq '.'`