<?php

class SoapCest
{
    public function wsdlIsGenerated(AcceptanceTester $I)
    {
        $I->sendGET('api/soap');
        $I->seeResponseCodeIs(200);
        $I->seeResponseContains('definitions');
        $I->seeResponseContains('soapTest');
        $I->seeResponseContains('SoapModel');
    }
}
