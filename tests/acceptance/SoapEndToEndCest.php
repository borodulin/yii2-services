<?php

/**
 * Calls the service through real HTTP servers with SoapClient:
 * one with YII_DEBUG on, one with YII_DEBUG off (production).
 * Requires ext-soap.
 */
class SoapEndToEndCest
{
    private $processes = [];
    private $logs = [];
    private $debug = 'http://127.0.0.1:8765';
    private $prod = 'http://127.0.0.1:8766';

    public function _before(AcceptanceTester $I)
    {
        if (!extension_loaded('soap')) {
            throw new \PHPUnit\Framework\SkippedTestError('ext-soap is required');
        }
        $this->start(8765, '1');
        $this->start(8766, '0');
    }

    private function start($port, $debug)
    {
        $root = dirname(__DIR__) . '/app/web';
        $log = tempnam(sys_get_temp_dir(), 'soap-server-');
        $env = getenv();
        $env['YII_DEBUG'] = $debug;
        $this->processes[] = proc_open(
            [PHP_BINARY, '-d', 'output_buffering=0', '-d', 'display_errors=stderr', '-S', "127.0.0.1:$port", $root . '/index.php'],
            [['pipe', 'r'], ['file', '/dev/null', 'w'], ['file', $log, 'w']],
            $pipes,
            null,
            $env
        );
        $this->logs[] = $log;
        for ($i = 0; $i < 50 && !@fsockopen('127.0.0.1', $port); $i++) {
            usleep(100000);
        }
    }

    public function _after(AcceptanceTester $I)
    {
        foreach ($this->processes as $process) {
            proc_terminate($process);
            proc_close($process);
        }
        foreach ($this->logs as $log) {
            @unlink($log);
        }
        $this->processes = $this->logs = [];
    }

    private function client($base, $action = 'soap')
    {
        return new SoapClient($base . '/api/' . $action, ['cache_wsdl' => WSDL_CACHE_NONE, 'exceptions' => true, 'trace' => true]);
    }

    private function fault(callable $call)
    {
        try {
            $call();
        } catch (SoapFault $fault) {
            return $fault;
        }
        throw new \RuntimeException('SoapFault expected');
    }

    private function serverLog()
    {
        clearstatcache();
        return implode("\n", array_map('file_get_contents', $this->logs));
    }

    public function soapCallWorks(AcceptanceTester $I)
    {
        $client = $this->client($this->debug);
        $I->assertEquals(5, $client->add(2, 3));
        $I->assertStringContainsString('text/xml', $client->__getLastResponseHeaders());
    }

    /**
     * SoapServer output is returned as the Yii response: before 1.7 every call
     * raised HeadersAlreadySentException after the response was sent.
     */
    public function noHeadersAlreadySent(AcceptanceTester $I)
    {
        $I->assertEquals(5, $this->client($this->prod)->add(2, 3));
        $I->assertEquals(5, $this->client($this->debug)->add(2, 3));
        $this->fault(function () {
            $this->client($this->prod)->internalError();
        });
        $I->assertStringNotContainsString('HeadersAlreadySent', $this->serverLog());
    }

    /**
     * Before 1.6 SoapServer fetched its WSDL from a URL built from the Host header (SSRF).
     */
    public function hostHeaderDoesNotAffectSoapServer(AcceptanceTester $I)
    {
        $envelope = '<?xml version="1.0"?>'
            . '<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ns1="urn:conquer\services\tests\app\controllers\ApiControllerwsdl">'
            . '<SOAP-ENV:Body><ns1:add><a>40</a><b>2</b></ns1:add></SOAP-ENV:Body></SOAP-ENV:Envelope>';
        $response = file_get_contents($this->debug . '/api/soap?ws=1', false, stream_context_create(['http' => [
            'method' => 'POST',
            'header' => "Host: attacker.invalid:1\r\nContent-Type: text/xml; charset=utf-8\r\nSOAPAction: \"add\"",
            'content' => $envelope,
            'ignore_errors' => true,
        ]]));
        $I->assertStringContainsString('42', $response);
        $I->assertStringNotContainsString('Parsing WSDL', $response);
    }

    public function internalErrorsAreHiddenInProduction(AcceptanceTester $I)
    {
        $client = $this->client($this->prod);
        $fault = $this->fault(function () use ($client) {
            $client->internalError();
        });
        $I->assertSame('Internal server error', $fault->getMessage());
        $I->assertStringNotContainsString('SQLSTATE', $client->__getLastResponse());
        $I->assertStringContainsString('500', $client->__getLastResponseHeaders());

        $fault = $this->fault(function () use ($client) {
            $client->phpError();
        });
        $I->assertSame('Internal server error', $fault->getMessage());
    }

    public function userFacingErrorsAreSent(AcceptanceTester $I)
    {
        $client = $this->client($this->prod);
        $fault = $this->fault(function () use ($client) {
            $client->userError();
        });
        $I->assertSame('Order not found', $fault->getMessage());
        $I->assertSame('UserException', $fault->faultcode);

        $fault = $this->fault(function () use ($client) {
            $client->soapFault();
        });
        $I->assertSame('Invalid order number', $fault->getMessage());
        $I->assertSame('SOAP-ENV:Client', $fault->faultcode);
    }

    public function internalErrorsAreShownInDebug(AcceptanceTester $I)
    {
        $fault = $this->fault(function () {
            $this->client($this->debug)->internalError();
        });
        $I->assertStringContainsString('SQLSTATE', $fault->getMessage());
        $I->assertSame('RuntimeException', $fault->faultcode);
    }

    /**
     * A type referencing itself used to make WSDL generation recurse until memory was exhausted.
     */
    public function recursiveTypes(AcceptanceTester $I)
    {
        foreach (['soap', 'doc'] as $action) {
            $client = $this->client($this->prod, $action);
            $root = $action === 'doc' ? $client->tree()->treeResult : $client->tree();
            $I->assertSame('root', $root->name);
            $children = is_array($root->children) ? $root->children : [$root->children->item ?? $root->children];
            $I->assertSame('child', $children[0]->name);
        }

        $author = new stdClass();
        $author->name = 'Author';
        $book = new stdClass();
        $book->title = 'Book';
        $book->author = $author;
        $author->books = [$book, $book];
        $I->assertEquals(2, $this->client($this->prod)->countBooks($author));
    }
}
