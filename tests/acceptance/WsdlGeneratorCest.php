<?php

use conquer\services\WsdlGenerator;
use conquer\services\tests\app\controllers\ApiController;
use conquer\services\tests\app\models\CollidingProvider;
use yii\base\InvalidConfigException;

class WsdlGeneratorCest
{
    private function schema($providerClass, $config = [])
    {
        $wsdl = (new WsdlGenerator($config))->generateWsdl($providerClass, 'http://localhost/');
        $dom = new DOMDocument();
        $dom->loadXML($wsdl);
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('xsd', 'http://www.w3.org/2001/XMLSchema');
        return $xpath;
    }

    private function typeNames(DOMXPath $xpath)
    {
        $names = [];
        foreach ($xpath->query('//xsd:schema/xsd:complexType/@name') as $attr) {
            $names[] = $attr->value;
        }
        return $names;
    }

    public function selfAndMutualReferences(AcceptanceTester $I)
    {
        foreach (['rpc', 'document'] as $style) {
            $xpath = $this->schema(ApiController::class, ['bindingStyle' => $style]);
            $names = $this->typeNames($xpath);
            foreach (['Node', 'NodeArray', 'Author', 'Book', 'BookArray'] as $type) {
                // each type is defined exactly once
                $I->assertSame(1, count(array_keys($names, $type)), "$style: $type");
            }
            $I->assertSame('tns:Node', $xpath->evaluate('string(//xsd:complexType[@name="Node"]//xsd:element[@name="parent"]/@type)'));
            $I->assertSame('tns:NodeArray', $xpath->evaluate('string(//xsd:complexType[@name="Node"]//xsd:element[@name="children"]/@type)'));
            $I->assertSame('tns:Author', $xpath->evaluate('string(//xsd:complexType[@name="Book"]//xsd:element[@name="author"]/@type)'));
        }
    }

    public function sameShortNameIsReported(AcceptanceTester $I)
    {
        $I->expectThrowable(InvalidConfigException::class, function () {
            (new WsdlGenerator())->generateWsdl(CollidingProvider::class, 'http://localhost/');
        });
    }

    public function htmlDocsAreEscaped(AcceptanceTester $I)
    {
        $generator = new WsdlGenerator();
        $generator->generateWsdl(ApiController::class, 'http://localhost/');
        $html = $generator->buildHtmlDocs(true);
        $I->assertStringContainsString('<h3>Object: Node</h3>', $html);
        $I->assertStringContainsString('<td>tns:NodeArray</td>', $html);
        $I->assertStringNotContainsString('{(', $html);
        $I->assertStringNotContainsString('\n', $html);
    }
}
