CoinRemitter Crypto Payment Gateway
===

Coinremitter Official Bitcoin/Altcoin Payment Gateway for Opencart3. Accept Bitcoin, BitcoinCash, Ethereum, Litecoin, Dogecoin, Ripple, Tether, Dash etc.

**What is Crypto Payment Gateway?**

There are ample of Crypto Coins available on crypto payment gateways. You can pick one of them and create a wallet of that coins and purchase things from individual’s websites who are accepting payment in crypto coins though. Regardless, All these websites have their own API in order to accept payment from buyers.

Apart from centralized currencies this option creates a traffic for sellers who are willing to do payments in crypto coins. In contrast, doing a payment in crypto coins offer buyers a great market  reputation and has left a foremost impact on sellers and it will also benefit to buyers & sellers if they choose **Coinremitter: Crypto Payment Gateway** as their payment method in doing a business in crypto coins.



Requirements for integration
---
* For the Integration process with Coinremitter, users must require to have  Opencart version 3.x
* If you don’t have an account on Coinremitter, then make sure to make it one

Installation of plugin
---
1. First! Download coinremitter opencart plugin in ocmod (downloaded file name will be coinremitter.ocmod.zip)
2. Go to opencart admin left panel -> Extensions -> installer -> click on upload -> select coinremitter.ocmod.zip from your path and upload it.
3. Go to opencart admin left panel -> Extensions -> Extensions -> select 'payments' from "choose the extension type" selection. You will see all payment methods extension there. Find 'Coinremitter' and click on '+' at end of the same line to install it.
4. Now, go to opencart admin left panel -> Extensions -> Modification -> click refresh button (right upper corner). You will see that 'coinremitter' menu will be added at bottom of your opencart admin left panel.
5. Plugin installed in your opencart store, Follow below instructions to fully activate it.

Plugin Configuration
---
* Go to admin panel left panel -> Extensions -> Extensions -> select 'payment' from "choose the extension type" selection. Find 'Coinremitter' payment extension and click on 'pencil' at end of the same line to edit it.
* On that page,you will find Configuration options of **Coinremitter CryptoPayment**. 
* In the 'Edit Coinremitter box' you will see multiple options to fill in.
* You will find the first option "Extension Status". Select it to **Enabled**.
* In the second option you can create your own **Title** if you need. It will display to user on checkout page
* In the **Description** tab you can add some notes to tell your customer some meaningful things before the customer makes any step during checkout. 
* Set **Exchange Rate .** Default is 1.
* Set **Set Invoice Expiry**. It is in minutes. So if you set value 30 then the created invoice will be expired after 30 minutes.
* In the last tab of **Order status** you can select one of your own status about what you want to show to customers when they successfully made out payment. 
(select appropriately because it will appear once payment gets done)

Create Wallet
---
Click **Coinremitter** menu on left panel of admin panel

* Now you are on the **Wallet List - Coinremitter** page.
* You’ll find the **Add Wallet** button on the right top of the page. Click on it.
* After clicking on the add wallet a new page will appear where you’ll see multiple options like **Coin, API key, Password**.
* In the first option of **Coin** select your coin from which you want to create your crypto wallet. 
* Now go to [*Coinremitter*](https://coinremitter.com) website and login to your account and get your API key from there. If you find any trouble to get your api then [**click here**](https://blog.coinremitter.com/how-to-get-api-key-and-password-of-coinremitter-wallet/) to get the idea.
* Get back to the Opencart coinremitter page and select one of your Coin. Paste API key in the box and fill your Password in the box.
* Click on the **Save** on right top of the page.
 
![Coinremitter-Plugin-Save-wallet](https://coinremitter.com/assets/img/screenshots/opencart/wallet_add.PNG)

* Congratulations! You have now successfully created your wallet.


> **Note:**

> - You can also see your other wallet list and can Edit/Delete your wallets. To 'edit' click **Pencil** button in 'action' cloumn. To 'Delete' select the wallet which you want to delete by clicking the **checkbox** on very first column on wallet list and then click **Delete** at top right corner in wallet list. See below images.
> - You can also refresh your wallet balance by clicking **refresh** button at right top corner in **wallet list** page

![Coinremitter-Plugin-wallet-list](https://coinremitter.com/assets/img/screenshots/opencart/wallet_list.PNG)

![Coinremitter-Plugin-wallet-edit-view](https://coinremitter.com/assets/img/screenshots/opencart/edit_wallet.PNG)

You have successfully activated coinremitter plugin.

How to make payment
---
* Once a customer creates an order and fills all the mandatory details, the system will take them on the payment page.
* You will see **Pay Using Cryptocurrency** option. Click on it. Click 'Continue' button on right bottom corner.
* Select one of your coin wallets from you want to pay for your product and click on **Confirm**.

![Coinremitter-Plugin-make-payment-option-page](https://coinremitter.com/assets/img/screenshots/opencart/checkout_option.PNG)

![Coinremitter-Plugin-make-payment-confirm-page](https://coinremitter.com/assets/img/screenshots/opencart/checkout_confirm.PNG)

* On the very next moment the system will automatically generate an **Invoice** which will appear on your screen.

![Coinremitter-Plugin-inovice-page](https://coinremitter.com/assets/img/screenshots/opencart/invoice.PNG)

* Copy **Payment address** from generated invoice and pay exact amount from your personal wallet. Once you transfer to this address, it requires enough confirmation to mark order as paid. It will automatically redirect to the success page once payment is confirmed on blockchain.

![Coinremitter-Plugin-thank-you-page](https://coinremitter.com/assets/img/screenshots/opencart/success.PNG) 

* Congratulations! You have now successfully paid for your product. 

Check order details
---
* Go to your **Admin Panel** menu and click on **Sales**, dropdown opens and click on **Orders**.
* Once you reach the **Orders** page you will see your multiple orders list. Select one of these orders. Make sure that order is paid using coinremitter payment option.
* Click on the **view** from one order and you will redirected to the order view page. 
* Scroll down to **Order History**.In **History**tab, you can see the details about payment in **comment** column.

![Coinremitter-Plugin-payment-detail](https://coinremitter.com/assets/img/screenshots/opencart/payment_detail.PNG) 