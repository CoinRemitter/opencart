CoinRemitter Plugin For Opencart
===

Coinremitter is a [crypto payment processor](https://coinremitter.com). Accept Bitcoin, Bitcoin Cash, Litecoin, Dogecoin, Dash, Tron, Binance ,Tether USD ERC20,Tether USD TRC20 etc.View all supported currency [here](https://coinremitter.com/supported-currencies).

**What Is Crypto Payment Gateway?**

There are ample of Crypto Coins available on crypto payment gateways. You can pick one of them and create a wallet of that coins and purchase things from individual’s websites who are accepting payment in crypto coins though. Regardless, All these websites have their own API in order to accept payment from buyers.

Apart from centralized currencies this option creates a traffic for sellers who are willing to do payments in crypto coins. In contrast, doing a payment in crypto coins offer buyers a great market  reputation and has left a foremost impact on sellers and it will also benefit to buyers & sellers if they choose **Coinremitter: Crypto Payment Gateway** as their payment method in doing a business in crypto coins.



Prerequisites
---
* For the Integration process with Coinremitter, users must require to have  Opencart version 4.x
* If you don’t have an account on Coinremitter, then make sure to [*sign up*](https://merchant.coinremitter.com/signup). 

Installation Of Plugin
---
1. **Download coinremitter opencart plugin** 
	
	a. **Download from GitHub.**<br> 
		* Download zip file from this repo and make sure that you download the latest version of this plugin. Click here for the [latest release](https://github.com/CoinRemitter/opencart/releases).<br> 
		* Make sure that the release is compatible with your Opencart version.<br> 
		* Extract the zip file. Now select all the folders like admin,catalog, install.json and compress them and name it as coinremitter.ocmod.zip .<br>
	
	b. **Download from OpenCart.**<br> 
		* Visit this url for official [opencart coinremitter plugin](https://www.opencart.com/index.php?route=marketplace/extension/info&extension_id=39007)<br> 
		* You will get zip file once your download gets finished, Make sure that the file name must be coinremitter.ocmod.zip and it must be compatible with your Opencart version.
		
2. Go to the admin panel left sidebar -> Extensions -> installer -> click on upload -> select **coinremitter.ocmod.zip** from your path and upload it

![Coinremitter-Plugin-extension-installer](https://coinremitter.com/assets/img/screenshots/opencart/extension_installer.png)

3. After uploading the file seek for **‘Coinremitter for checkout’** then click **‘+’** at the end of the same row to add the coinremitter plugin.
4. Go to the admin panel left sidebar -> Extensions -> Extensions -> select **'payments'** from **"choose the extension type"** selection. You will see all payment methods extension there. Find **'Coinremitter'** and click on **'+'** at end of the same line to enable it.

![Coinremitter-Plugin-enable-payment-option](https://coinremitter.com/assets/img/screenshots/opencart/payment_select.png)

5. Go to the admin panel left sidebar -> Extensions -> Extensions -> select **'Modules'** from **"choose the extension type"** selection. You will see extensions of all the modules there.Find **'Coinremitter'** and click **'+'** at the end of the same row to enable it. Then find **'Add Coinremitter Menu'** and click **'+'** at end of the row to add the Coinremitter menu in the left sidebar.

![Coinremitter-Plugin-enable-module-option](https://coinremitter.com/assets/img/screenshots/opencart/module_select.png)

6. Plugin is installed to your Opencart store, follow the below instructions to fully activate it.

Plugin Configuration
---
* Go to the admin panel left sidebar -> Extensions -> Extensions -> select **'payment'** from **"choose the extension type"** selection. Find **'Coinremitter'** payment extension and click on **'pencil'** at end of the same line to edit it.

![Coinremitter-Plugin-configuration](https://coinremitter.com/assets/img/screenshots/opencart/configuration.png)

* You will find the first option **"Extension Status"**. Select it to **Enabled**.
* In the second option you can create your own **Title** if you need. It will display to user on checkout page
* In the **Description** tab you can add some notes to tell your customer some meaningful things before the customer makes any step during checkout. 
* Set **Set Invoice Expiry**. It is in minutes. So if you set value 30 then the created invoice will be expired after 30 minutes.
* In the last tab of **Order status** you can select one of your own status about what you want to show to customers when they successfully made out payment. 
(select appropriately because it will appear once payment gets done)

Create Wallet
---
Click **Coinremitter** menu on admin panel left sidebar

* Now you are on the **Wallet List** page.
* You’ll find the **Add Wallet** button on the right top of the page. Click on it.
* After clicking on the add wallet a new page will appear where you’ll see multiple options like **API key, Password**.
* Go to [*Coinremitter*](https://coinremitter.com) website and login to your account and get your API key from there. If you find any trouble to get your api then [**click here**](https://blog.coinremitter.com/how-to-get-api-key-and-password-of-coinremitter-wallet/) to get the idea.
* Get back to the Opencart coinremitter page and paste API key in the box and fill your Password in the box.
* Set the exchange rate multiplier. The default is set to '1'.
* Set the minimum invoice value in your website's base fiat currency. The default is set to '0'.
* Click on the **Save** on right top of the page.
 
![Coinremitter-Plugin-Save-wallet](https://coinremitter.com/assets/img/screenshots/opencart/wallet_add.png)

* Congratulations! You have now successfully created your wallet.


> **Note:**

> - You can also see your other wallet list and can Edit/Delete your wallets. To 'edit' click **Pencil** button in **'action'** cloumn. To **'Delete'** select the wallet which you want to delete by clicking the **checkbox** on very first column on wallet list and then click **Delete** at top right corner in wallet list. See below images.
> - You can also refresh your wallet balance by clicking **refresh** button at right top corner in **wallet list** page

![Coinremitter-Plugin-wallet-list](https://coinremitter.com/assets/img/screenshots/opencart/wallet_list.png)

You have successfully activated coinremitter plugin.

How to make payment
---
* Once a customer creates an order and fills all the mandatory details, the system will take them on the payment page.
* You will see **Pay Using Cryptocurrency** option. Click on it. Click 'Continue' button on right bottom corner.
* Select one of your coin wallets from you want to pay for your product and click on **Confirm**.

![Coinremitter-Plugin-make-payment-option-and-coinfirm-page](https://coinremitter.com/assets/img/screenshots/opencart/checkout_option.png)

* On the very next moment the system will automatically generate an **Invoice** which will appear on your screen.

![Coinremitter-Plugin-inovice-page](https://coinremitter.com/assets/img/screenshots/opencart/invoice.png)

* Copy **Payment address** from generated invoice and pay exact amount from your personal wallet. Once you transfer to this address, it requires enough confirmation to mark order as paid. It will automatically redirect to the success page once payment is confirmed on blockchain.

![Coinremitter-Plugin-thank-you-page](https://coinremitter.com/assets/img/screenshots/opencart/success.png) 

* Congratulations! You have now successfully paid for your product. 

Check order details
---
* Go to your **Admin Panel** menu and click on **Sales**, dropdown opens and click on **Orders**.
* Once you reach the **Orders** page you will see your multiple orders list. Select one of these orders. Make sure that order is paid using coinremitter payment option.
* Click on the **view** from one order and you will redirected to the order view page. 
* Scroll down to **Order History**.In **History** tab, you can see the details about payment in **comment** column.

![Coinremitter-Plugin-payment-detail](https://coinremitter.com/assets/img/screenshots/opencart/payment_detail.png) 

Uninstall Plgin
---
1. Go to the admin panel left sidebar -> Extensions -> Extensions -> select **'Modules'** from **"choose the extension type"** selection. You will see extensions of all the modules there.Find **'Coinremitter'** and click **'-'** at the end of the same row to disable it. Then find **'Add Coinremitter Menu'** and click **'-'** at end of the row to remove the Coinremitter menu in the left sidebar.
2. Go to the admin panel left sidebar -> Extensions -> Extensions -> select **'payments'** from **"choose the extension type"** selection. You will see all payment methods extension there. Find **'Coinremitter'** and click on **'-'** at end of the same line to disable it.
3. Go to the admin panel left sidebar -> Extensions -> installer -> file, then seek for **'Coinremitter for checkout'** then click **'-'** at the end of the same row to disable the Coinremitter plugin then click on **'Delete symbol'**. to remove the Coinremitter plugin.

