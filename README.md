Magento payment module installation instructions
================================================

This module has been tested with Magento 1.7

Install Magento shopping cart
This can be done vis SVN:
http://svn.magentocommerce.com/source/branches/1.7

Unzip/copy all the files from this module distribution into your magento codebase

In magento admin go System -> Configuration
then Sales -> Payment methods from LHS menu
Expand the 'zipbit' module section.
Note: You might need to flush magento's cache to see this option
Enter your config details:

Enabled: yes
Title: <this is the shop customers will see on the payment methods screen>
Merchant key: <enter your zipbit merchant key>
Payment Applicable From: <select which countries can use this option, or all>

Remember to set return url and ipn in merchant admin.

Example return URL:
http://www.example.com/magento/index.php/zipbit/payment/response

Example IPN URL:
http://www.example.com/magento/index.php/zipbit/payment/ipn

You can change the content for the return page on magento by editing the file:
app/design/frontend/base/default/template/zipbit/response.phtml