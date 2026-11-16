<?php
require "C:/Invoiz/devops/lab-act-3/scripts/test-bootstrap.php";
$loader = require "C:/Invoiz/vendor/autoload.php";
$loader->addClassMap([
'App\\Support\\PayoutAccounts' => 'C:/Invoiz/devops/lab-act-3/.runtime/baseline-classes/app_Support_PayoutAccounts.php',
'App\\Support\\ReturnServiceEarnings' => 'C:/Invoiz/devops/lab-act-3/.runtime/baseline-classes/app_Support_ReturnServiceEarnings.php',
'App\\Services\\ReturnTripPricingService' => 'C:/Invoiz/devops/lab-act-3/.runtime/baseline-classes/app_Services_ReturnTripPricingService.php',
'App\\Services\\FulfillmentWorkflow' => 'C:/Invoiz/devops/lab-act-3/.runtime/baseline-classes/app_Services_FulfillmentWorkflow.php',
'App\\Http\\Controllers\\Logistics\\Api\\RiderController' => 'C:/Invoiz/devops/lab-act-3/.runtime/baseline-classes/app_Http_Controllers_Logistics_Api_RiderController.php',
'App\\Http\\Controllers\\Logistics\\Api\\RiderDeliveryController' => 'C:/Invoiz/devops/lab-act-3/.runtime/baseline-classes/app_Http_Controllers_Logistics_Api_RiderDeliveryController.php',
'App\\Http\\Controllers\\Logistics\\Api\\RiderEarningsController' => 'C:/Invoiz/devops/lab-act-3/.runtime/baseline-classes/app_Http_Controllers_Logistics_Api_RiderEarningsController.php',
'App\\Http\\Controllers\\Logistics\\Api\\HubCourierController' => 'C:/Invoiz/devops/lab-act-3/.runtime/baseline-classes/app_Http_Controllers_Logistics_Api_HubCourierController.php',
'App\\Http\\Controllers\\IncomingRemittanceController' => 'C:/Invoiz/devops/lab-act-3/.runtime/baseline-classes/app_Http_Controllers_IncomingRemittanceController.php',
'App\\Support\\RemittanceFeed' => 'C:/Invoiz/devops/lab-act-3/.runtime/baseline-classes/app_Support_RemittanceFeed.php',
'App\\Http\\Controllers\\Seller\\OrderController' => 'C:/Invoiz/devops/lab-act-3/.runtime/baseline-classes/app_Http_Controllers_Seller_OrderController.php',
]);
