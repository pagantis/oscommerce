<?php

namespace Test\Buy;

use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Test\PagantisOscommerceTest;

/**
 * Class AbstractBuy
 * @package Test\Buy
 */
abstract class AbstractBuy extends PagantisOscommerceTest
{
    /**
     * Product name
     */
    const PRODUCT_NAME = 'Matrox G200 MMS';

    /**
     * Correct purchase message
     */
    const CORRECT_PURCHASE_MESSAGE = 'YOUR ORDER HAS BEEN RECEIVED.';

    /**
     * Canceled purchase message
     */
    const CANCELED_PURCHASE_MESSAGE = 'YOUR ORDER HAS BEEN CANCELED.';

    /**
     * Shopping cart message
     */
    const SHOPPING_CART_MESSAGE = 'SHOPPING CART';

    /**
     * Empty shopping cart message
     */
    const EMPTY_SHOPPING_CART = 'SHOPPING CART IS EMPTY';

    /**
     * Pagantis Order Title
     */
    const PAGANTIS_TITLE = 'Paga+Tarde';

    /**
     * Notification route
     */
    const NOTIFICATION_FOLDER = '/pagantis/notify';

    /**
     * Buy unregistered
     */
    public function prepareProductAndCheckout()
    {
        $this->goToProductPage();
        $this->addToCart();
    }

    /**
     * testAddToCart
     */
    public function addToCart()
    {
        $addToCartButtonSearch = WebDriverBy::id('tdb4');
        $condition = WebDriverExpectedCondition::visibilityOfElementLocated($addToCartButtonSearch);
        $this->waitUntil($condition);
        $addToCartButtonElement = $this->webDriver->findElement($addToCartButtonSearch);
        $this->webDriver->executeScript("arguments[0].scrollIntoView(true);", array($addToCartButtonElement));
        $addToCartButtonElement->click();


        $buyButtonSearch = WebDriverBy::id('tdb5');
        $condition = WebDriverExpectedCondition::visibilityOfElementLocated($buyButtonSearch);
        $this->waitUntil($condition);
        $buyButtonElement = $this->webDriver->findElement($buyButtonSearch);
        $this->webDriver->executeScript("arguments[0].scrollIntoView(true);", array($buyButtonElement));
        $buyButtonElement->click();
    }

    /**
     * Go to the product page
     */
    public function goToProductPage()
    {
        $this->webDriver->get(self::OSCURL);
        $productGridSearch = WebDriverBy::className('contentText');
        $productLinkSearch = $productGridSearch->linkText(strtoupper(self::PRODUCT_NAME));

        $this->webDriver->wait()->until(
            WebDriverExpectedCondition::elementToBeClickable(
                $productLinkSearch
            )
        );
        $productLinkElement = $this->webDriver->findElement($productLinkSearch);
        $this->webDriver->executeScript("arguments[0].scrollIntoView(true);", array($productLinkElement));
        sleep(3);
        $productLinkElement->click();
        $this->assertSame(
            self::PRODUCT_NAME . ', ' . self::TITLE,
            $this->webDriver->getTitle()
        );
    }

    /**
     * Fill the shipping method information
     */
    public function fillPaymentMethod()
    {
        sleep(5);

        $reviewStepSearch = WebDriverBy::id('p_method_pagantis');
        $this->webDriver->wait()->until(
            WebDriverExpectedCondition::elementToBeClickable($reviewStepSearch)
        );
        $this->findById('p_method_pagantis')->click();

        $this->webDriver->executeScript("payment.save()");
        $reviewStepSearch = WebDriverBy::id('review-buttons-container');
        $this->webDriver->wait()->until(
            WebDriverExpectedCondition::visibilityOfElementLocated($reviewStepSearch)
        );

        $this->assertTrue(
            (bool) WebDriverExpectedCondition::visibilityOfElementLocated($reviewStepSearch)
        );
    }

    /**
     * Fill the shipping method information
     */
    public function fillShippingMethod()
    {
        sleep(5);

        $this->findById('s_method_flatrate_flatrate')->click();
        $this->webDriver->executeScript('shippingMethod.save()');

        $checkoutStepPaymentMethodSearch = WebDriverBy::id('checkout-payment-method-load');
        $this->webDriver->wait()->until(
            WebDriverExpectedCondition::visibilityOfElementLocated($checkoutStepPaymentMethodSearch)
        );
        $this->assertTrue(
            (bool) WebDriverExpectedCondition::visibilityOfElementLocated($checkoutStepPaymentMethodSearch)
        );
    }


    /**
     * Complete order and open Pagantis (redirect or iframe methods)
     *
     * @throws \Facebook\WebDriver\Exception\NoSuchElementException
     * @throws \Facebook\WebDriver\Exception\TimeOutException
     */
    public function goToPagantis()
    {
        sleep(5);

        $this->webDriver->executeScript('review.save()');
    }

    /**
     * Close previous pagantis session if an user is logged in
     *
     * @throws \Facebook\WebDriver\Exception\NoSuchElementException
     * @throws \Facebook\WebDriver\Exception\TimeOutException
     */
    public function logoutFromPagantis()
    {
        // Wait the page to render (check the simulator is rendered)
        $this->webDriver->wait()->until(
            WebDriverExpectedCondition::elementToBeClickable(
                WebDriverBy::name('minusButton')
            )
        );
        // Check if user is logged in in Pagantis
        $closeSession = $this->webDriver->findElements(WebDriverBy::name('one_click_return_to_normal'));
        if (count($closeSession) !== 0) {
            //Logged out
            $continueButtonSearch = WebDriverBy::name('one_click_return_to_normal');
            $continueButtonElement = $this->webDriver->findElement($continueButtonSearch);
            $continueButtonElement->click();
        }
    }

    /**
     * Verify That UTF Encoding is working
     */
    public function verifyUTF8()
    {
        $paymentFormElement = WebDriverBy::className('FieldsPreview-desc');
        $condition = WebDriverExpectedCondition::visibilityOfElementLocated($paymentFormElement);
        $this->webDriver->wait()->until($condition);
        $this->assertTrue((bool) $condition);
        $this->assertSame(
            $this->configuration['firstname'] . ' ' . $this->configuration['lastname'],
            $this->findByClass('FieldsPreview-desc')->getText()
        );
    }

    /**
     * Check if the purchase was in the myAccount panel and with Processing status
     *
     * @param string $statusText
     *
     * @throws \Facebook\WebDriver\Exception\NoSuchElementException
     * @throws \Facebook\WebDriver\Exception\TimeOutException
     */
    public function checkLastPurchaseStatus($statusText = 'Processing')
    {
        $accountMenu = WebDriverBy::cssSelector('.account-cart-wrapper a.skip-link.skip-account');
        $this->clickElement($accountMenu);

        $myAccountMenu = WebDriverBy::cssSelector('#header-account .first a');
        $this->clickElement($myAccountMenu);

        $this->webDriver->wait()->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(
                WebDriverBy::cssSelector('.box-account.box-recent')
            )
        );

        $status = $this->findByCss('.box-account.box-recent .data-table.orders .first .status em')->getText();
        $this->assertTrue(($status == $statusText));
    }

    /**
     * Check purchase return message
     *
     * @param string $message
     *
     * @throws \Facebook\WebDriver\Exception\NoSuchElementException
     * @throws \Facebook\WebDriver\Exception\TimeOutException
     */
    public function checkPurchaseReturn($message = '')
    {
        // Check if all goes good
        $this->webDriver->wait()->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(
                WebDriverBy::cssSelector('.page-title h1')
            )
        );
        $successMessage = $this->findByCss('.page-title h1');
        $this->assertContains(
            $message,
            $successMessage->getText()
        );
    }
}