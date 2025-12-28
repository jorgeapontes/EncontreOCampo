# 🌾 Encontre o Campo: Agricultural Products Marketplace

[![Project Status](https://img.shields.io/badge/Status-In%20Development-blue)](https://github.com/seu-usuario/seu-repositorio)


>An online commerce platform that connects rural producers (Sellers) directly to buyers and companies (Buyers), facilitating the negotiation and acquisition of agricultural products with a focus on transparency and efficiency.

##  Key Features

"Encontre o Campo" offers a complete sales and negotiation ecosystem:

###   Seller (Rural Producer)
* **Listing Management:** Registration, editing and tracking of products and their inventory.
* **Subscription Plans:** Plan system to determine listing limits..
* **Negotiation and Proposals:** Viewing, accepting, rejecting or sending purchase counterproposals.


###   Buyer (Companies/Consumers)
* **Advanced Search:** Filters by category, price and location to find specific products.
* **Proposal Creation:** Ability to negotiate price, quantity and payment/delivery conditions with the seller before finalizing the purchase.
* **Proposal Dashboard:** Tracking the status of all sent and ongoing proposals.
  
### Architecture and Payment
* **Price Precedence:** Final price application logic (Proposal > Discount > Normal Price).
* **Order Freezings:** Recording of the final transaction (orders table) at the time of purchase, ensuring value integrity even if the product price is changed.
* **Mercado Pago Integration:** Use of Mercado Pago API for purchases/sales and subscriptions.
---

## Prerequisites

* [PHP] (version 7.4 or higher)
* [MySQL/MariaDB]
* [Composer] (To manage PHP dependencies)
* Web Server (Apache or Nginx, or use PHP's built-in server)

