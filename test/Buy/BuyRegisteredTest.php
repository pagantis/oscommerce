<?php

namespace Test\Buy;

use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Pagantis\ModuleUtils\Exception\AlreadyProcessedException;
use Pagantis\ModuleUtils\Exception\NoIdentificationException;
use Pagantis\ModuleUtils\Exception\QuoteNotFoundException;
use Pagantis\SeleniumFormUtils\SeleniumHelper;
use Httpful\Request;

/**
 * Class BuyRegisteredTest
 * @package Test\Buy
 *
 * @group oscommerce-buy-registered
 */
class BuyRegisteredTest extends AbstractBuy
{
    /**
     * @var String $orderUrl
     */
    public $orderUrl;

    /**
     * Test Buy Registered
     */
    public function testBuyRegistered()
    {
        $this->prepareProductAndCheckout();
        $this->login();
        $this->fillBillingInformation();
        $this->fillShippingMethod();
        $this->fillPaymentMethod();

        // get cart total price
        $cartPrice = WebDriverBy::cssSelector('#checkout-review-table tfoot tr.last .price');
        $this->webDriver->wait()->until(
            WebDriverExpectedCondition::presenceOfElementLocated(
                $cartPrice
            )
        );
        $cartPrice = $this->webDriver->findElement($cartPrice)->getText();
        // --------------------
        $this->goToPagantis();
        $this->verifyPagantis();
        $this->commitPurchase();
        $this->checkPurchaseReturn(self::CORRECT_PURCHASE_MESSAGE);
        $this->checkLastPurchaseStatus('Processing');

        // get registered purchase amount
        $checkoutPrice = WebDriverBy::cssSelector(
            '.box-account.box-recent .data-table.orders .first .total .price'
        );
        $this->webDriver->wait()->until(
            WebDriverExpectedCondition::presenceOfElementLocated(
                $checkoutPrice
            )
        );
        $checkoutPrice = $this->webDriver->findElement($checkoutPrice)->getText();
        //----------------------

        $this->assertTrue(($cartPrice == $checkoutPrice));
        $this->makeValidation();
        $this->quit();
    }

    /**
     * Fill the billing information
     */
    public function fillBillingInformation()
    {
        $this->webDriver->executeScript('billing.save()');
        $checkoutStepShippingMethodSearch = WebDriverBy::id('checkout-shipping-method-load');
        $condition = WebDriverExpectedCondition::visibilityOfElementLocated($checkoutStepShippingMethodSearch);
        $this->webDriver->wait()->until($condition);
        $this->assertTrue((bool) $condition);
    }

    /**
     * Login
     */
    public function login()
    {
        $this->findById('login-email')->clear()->sendKeys($this->configuration['email']);
        $this->findById('login-password')->clear()->sendKeys($this->configuration['password']);
        $this->findById('login-form')->submit();

        $billingAddressSelector = WebDriverBy::id('billing-address-select');
        $condition = WebDriverExpectedCondition::visibilityOfElementLocated($billingAddressSelector);
        $this->webDriver->wait()->until($condition);
        $this->assertTrue((bool) $condition);
    }

    /**
     * Commit Purchase
     * @throws \Exception
     */
    public function commitPurchase()
    {

        $condition = WebDriverExpectedCondition::titleContains(self::PAGANTIS_TITLE);
        $this->webDriver->wait(300)->until($condition, $this->webDriver->getCurrentURL());
        $this->assertTrue((bool)$condition, "PR32");

        // complete the purchase with redirect
        SeleniumHelper::finishForm($this->webDriver);
    }

    /**
     * Verify Pagantis
     *
     * @throws \Exception
     */
    public function verifyPagantis()
    {
        $condition = WebDriverExpectedCondition::titleContains(self::PAGANTIS_TITLE);
        $this->webDriver->wait(300)->until($condition, $this->webDriver->getCurrentURL());
        $this->assertTrue((bool)$condition, $this->webDriver->getCurrentURL());
    }

    public function makeValidation()
    {
        $this->checkConcurrency();
        $this->checkPagantisOrderId();
        $this->checkAlreadyProcessed();
    }


    /**
     * Check if with a empty parameter called order-received we can get a QuoteNotFoundException
     */
    protected function checkConcurrency()
    {
        $notifyUrl = self::OSCURL.self::NOTIFICATION_FOLDER.'?order=';
        $this->assertNotEmpty($notifyUrl, $notifyUrl);
        $response = Request::post($notifyUrl)->expects('json')->send();
        $this->assertNotEmpty($response->body->result, $response);
        $this->assertNotEmpty($response->body->status_code, $response);
        $this->assertNotEmpty($response->body->timestamp, $response);
        $this->assertContains(
            QuoteNotFoundException::ERROR_MESSAGE,
            $response->body->result,
            "PR=>".$response->body->result
        );
    }

    /**
     * Check if with a parameter called order-received set to a invalid identification,
     * we can get a NoIdentificationException
     */
    protected function checkPagantisOrderId()
    {
        $orderId=0;
        $notifyUrl = self::OSCURL.self::NOTIFICATION_FOLDER.'?order='.$orderId;
        $this->assertNotEmpty($notifyUrl, $notifyUrl);
        $response = Request::post($notifyUrl)->expects('json')->send();
        $this->assertNotEmpty($response->body->result, $response);
        $this->assertNotEmpty($response->body->status_code, $response);
        $this->assertNotEmpty($response->body->timestamp, $response);
        $this->assertEquals(
            $response->body->merchant_order_id,
            $orderId,
            $response->body->merchant_order_id.'!='. $orderId
        );
        $this->assertContains(
            NoIdentificationException::ERROR_MESSAGE,
            $response->body->result,
            "PR=>".$response->body->result
        );
    }

    /**
     * Check if re-launching the notification we can get a AlreadyProcessedException
     *
     * @throws \Httpful\Exception\ConnectionErrorException
     */
    protected function checkAlreadyProcessed()
    {
        $notifyUrl = self::OSCURL.self::NOTIFICATION_FOLDER.'?order=145000008';
        $response = Request::post($notifyUrl)->expects('json')->send();
        $this->assertNotEmpty($response->body->result, $response);
        $this->assertNotEmpty($response->body->status_code, $response);
        $this->assertNotEmpty($response->body->timestamp, $response);
        $this->assertNotEmpty($response->body->merchant_order_id, $response);
        $this->assertNotEmpty($response->body->pagantis_order_id, $response);
        $this->assertContains(
            AlreadyProcessedException::ERROR_MESSAGE,
            $response->body->result,
            "PR51=>".$response->body->result
        );
    }
}