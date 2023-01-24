# Commerce Two plugin for Craft CMS 4.x

Two integration for Craft CMS

![Screenshot](resources/img/plugin-logo.jpeg)

## Requirements

- Craft CMS version 4.0.0 or later
- Craft Commerce PRO version 4.0.0 or later

## Installation

1. Navigate to the root directory of your Craft project in the terminal:

        cd /path/to/project

2. Use composer to load the plugin:

        composer require netlab/commerce-two

3. In the Control Panel, navigate to Settings > Plugins and click the “Install” button for Commerce Two.

## Usage

This plugin enables integration with [Two](https://www.two.inc/) through a custom implementation of the [Omnipay](https://github.com/craftcms/commerce-omnipay/) gateway. However, it is not a plug-and-play solution and requires implementation steps to be performed using the controller actions provided for the checkout process.

## Configuration

The following steps must be taken to configure the plugin:
1. Go to the plugin settings page (admin/settings/plugins/commerce-two) and add your API credentials (Merchant ID, API keys, select the appropriate environment, and language for invoice generation). Consider using environment variables for storing sensitive information.
2. Create a new payment gateway in Craft Commerce (admin/commerce/settings/gateways) and set Two as the gateway. The plugin supports both Authorize Only and Purchase options. Note that for the Authorize Only option, you will need to manually capture the payment for each order.
3. Create a new field with the handle "phone" and add it to the User Address field layout (admin/settings/users/address-fields).

## Features

- Query companies by their names or organization numbers using the ```commerce-two/company-search``` action.
- Retrieve the address of a desired company using ```commerce-two/company-address```.
- Verify that a company is allowed to use Two as a payment provider using ```commerce-two/company-check``` action.
- If the company is accepted by Two, use ```commerce-two/set-company``` to save the company information to the cart object. This is a mandatory step as this information is used during API communication.
- If your website uses a custom implementation to handle billing or shipping addresses, use the ```commerce-two/set-customer-addresses``` endpoint to attach the information to the customer object. This is also mandatory as the plugin uses the built-in [Address handling](https://craftcms.com/docs/4.x/addresses.html) provided by Craft 4.

## Roadmap

* Consider a different approach for address handling to support the Commerce Lite edition.

Brought to you by [Netlab](https://netlab.no)
