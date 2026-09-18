SEWANA LANKA PROPERTY API

The redesigned home page loads its property cards from this JSON endpoint:

  api/properties.php

Filter listings by any valid Sri Lankan district:

  api/properties.php?district=Colombo

Successful responses include the listing count and a properties array with
the title, price, district, status, image, media count, detail link and
WhatsApp enquiry link.

If listings do not load, confirm that Apache and MySQL are both running and
that schema.sql has been imported into MySQL.
