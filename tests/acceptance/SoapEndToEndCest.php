<?php

/**
 * Calls the service through a real HTTP server with SoapClient.
 * Requires ext-soap.
 */
class SoapEndToEndCest
{
    private $process;
    private $base = 'http://127.0.0.1:8765';

    public function _before(AcceptanceTester $I)
    {
        if (!extension_loaded('soap')) {
            throw new \PHPUnit\Framework\SkippedTestError('ext-soap is required');
        }
        $root = dirname(__DIR__) . '/app/web';
        $this->process = proc_open(
            [PHP_BINARY, '-S', '127.0.0.1:8765', $root . '/index.php'],
            [['pipe', 'r'], ['file', '/dev/null', 'w'], ['file', '/dev/null', 'w']],
            $pipes
        );
        for ($i = 0; $i < 50 && !@fsockopen('127.0.0.1', 8765); $i++) {
            usleep(100000);
        }
    }

    public function _after(AcceptanceTester $I)
    {
        proc_terminate($this->process);
        proc_close($this->process);
    }

    public function soapCallWorks(AcceptanceTester $I)
    {
        $client = new SoapClient($this->base . '/api/soap', ['cache_wsdl' => WSDL_CACHE_NONE, 'exceptions' => true]);
        $I->assertEquals(5, $client->add(2, 3));
    }

    /**
     * Before 1.6 SoapServer fetched its WSDL from a URL built from the Host header (SSRF).
     */
    public function hostHeaderDoesNotAffectSoapServer(AcceptanceTester $I)
    {
        $envelope = '<?xml version="1.0"?>'
            . '<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ns1="urn:conquer\services\tests\app\controllers\ApiControllerwsdl">'
            . '<SOAP-ENV:Body><ns1:add><a>40</a><b>2</b></ns1:add></SOAP-ENV:Body></SOAP-ENV:Envelope>';
        $response = file_get_contents($this->base . '/api/soap?ws=1', false, stream_context_create(['http' => [
            'method' => 'POST',
            'header' => "Host: attacker.invalid:1\r\nContent-Type: text/xml; charset=utf-8\r\nSOAPAction: \"add\"",
            'content' => $envelope,
            'ignore_errors' => true,
        ]]));
        $I->assertStringContainsString('42', $response);
        $I->assertStringNotContainsString('Parsing WSDL', $response);
    }
}
