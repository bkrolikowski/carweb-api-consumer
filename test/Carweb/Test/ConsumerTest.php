<?php

namespace Carweb\Test;

use Carweb\Consumer;
use Carweb\Converter\DefaultConverter;

class ConsumerTest extends \PHPUnit_Framework_TestCase
{
    public function testConstructor()
    {
        $client = $this->getClient();
        $consumer = new Consumer($client, 'username', 'password', 'key', '0.31.1');
        $this->assertAttributeEquals($client, 'client', $consumer);
        $this->assertAttributeEquals('username', 'strUserName', $consumer);
        $this->assertAttributeEquals('password', 'strPassword', $consumer);
        $this->assertAttributeEquals('key', 'strKey1', $consumer);
    }

    public function testSetConverter()
    {
        $client = $this->getClient();
        $consumer = new Consumer($client, 'username', 'password', 'key', '0.31.1');
        $converter = $this->getMock('Carweb\Converter\ConverterInterface', array('convert'));
        $consumer->setConverter('someMethod', $converter);
    }

    public function testGetConverter()
    {
        $client = $this->getClient();
        $consumer = new Consumer($client, 'username', 'password', 'key', '0.31.1');

        $this->assertTrue($consumer->getConverter('someMethod') instanceof DefaultConverter);

        $converter = $this->getMock('Carweb\Converter\ConverterInterface', array('convert'));
        $consumer->setConverter('someMethod', $converter);

        $this->assertEquals($converter, $consumer->getConverter('someMethod'));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testSetConverterException()
    {
        $client = $this->getClient();
        $consumer = new Consumer($client, 'username', 'password', 'key', '0.31.1');

        $consumer->setConverter('someMethod', new \stdClass());
    }

    public function testFindByVRMFailoverDisabled()
    {
        $client = $this->getClient();
        $response = $this->getResponse(200, '<?xml version="1.0" encoding="utf-8"?><GetVehicles></GetVehicles>');

        $client
            ->expects($this->once())
            ->method('call')
            ->will($this->returnValue($response));

        $consumer = new Consumer($client, 'username', 'password', 'key', '0.31.1', null, true, false);

        $consumer->findByVRM('AA01 AAA', 'test', 'test');
        $this->assertFalse($consumer->isCachedResponse());
    }

    public function testFindByVRMFailoverEnabled()
    {
        $client = $this->getClient();
        $response = $this->getResponse(200, '<?xml version="1.0" encoding="utf-8"?><GetVehicles></GetVehicles>');

        $client
            ->expects($this->once())
            ->method('call')
            ->will($this->returnValue($response));

        $consumer = new Consumer($client, 'username', 'password', 'key', '0.31.1', null, true, true);

        $consumer->findByVRM('AA01 AAA', 'test', 'test');

        $this->assertFalse($consumer->isCachedResponse());
    }

    /**
     * @expectedException \Carweb\Exception\ValidationException
     */
    public function testFindByVRMInvalidVRM()
    {
        $client = $this->getClient();

        $consumer = new Consumer($client, 'username', 'password', 'key', '0.31.1');

        $consumer->findByVRM('AA00 AAA', 'test', 'test');

    }

    public function testFindByVRMWithCacheFalseAndFailoverDisabled()
    {
        $client = $this->getClient();
        $response = $this->getResponse(200, '<?xml version="1.0" encoding="utf-8"?><GetVehicles></GetVehicles>');

        $client
            ->expects($this->once())
            ->method('call')
            ->will($this->returnValue($response));

        $cache = $this->getMock('Carweb\Cache\CacheInterface', array('has','get','save','clear'));
        $cache
            ->expects($this->once())
            ->method('has')
            ->with('strB2BGetVehicleByVRM.AA01AAA')
            ->will($this->returnValue(false));

        $cache
            ->expects($this->once())
            ->method('save')
            ->with('strB2BGetVehicleByVRM.AA01AAA','<?xml version="1.0" encoding="utf-8"?><GetVehicles></GetVehicles>');

        $consumer = new Consumer($client, 'username', 'password', 'key', '0.31.1', $cache, true, false);

        $consumer->findByVRM('AA01 AAA', 'test', 'test');
        $this->assertFalse($consumer->isCachedResponse());
    }

    public function testFindByVRMWithCacheFalseAndFailoverEnabled()
    {
        $client = $this->getClient();
        $response = $this->getResponse(200, '<?xml version="1.0" encoding="utf-8"?><GetVehicles></GetVehicles>');

        $client
            ->expects($this->once())
            ->method('call')
            ->will($this->returnValue($response));

        $cache = $this->getMock('Carweb\Cache\CacheInterface', array('has','get','save','clear'));
        $cache
            ->expects($this->once())
            ->method('has')
            ->with('strB2BGetVehicleByVRM.AA01AAA')
            ->will($this->returnValue(false));

        $cache
            ->expects($this->once())
            ->method('save')
            ->with('strB2BGetVehicleByVRM.AA01AAA','<?xml version="1.0" encoding="utf-8"?><GetVehicles></GetVehicles>');

        $consumer = new Consumer($client, 'username', 'password', 'key', '0.31.1', $cache, true, true);

        $consumer->findByVRM('AA01 AAA', 'test', 'test');
        $this->assertFalse($consumer->isCachedResponse());
    }

    public function testFindByVRMWithCacheTrueAndFailoverDisabled()
    {
        $client = $this->getClient();

        $cache = $this->getMock('Carweb\Cache\CacheInterface', array('has','get','save','clear'));
        $cache
            ->expects($this->once())
            ->method('has')
            ->with('strB2BGetVehicleByVRM.AA01AAA')
            ->will($this->returnValue(true));

        $cache
            ->expects($this->once())
            ->method('get')
            ->with('strB2BGetVehicleByVRM.AA01AAA')
            ->will($this->returnValue('<?xml version="1.0" encoding="utf-8"?><GetVehicles></GetVehicles>'));

        $consumer = new Consumer($client, 'username', 'password', 'key', '0.31.1', $cache, true, false);

        $consumer->findByVRM('AA01 AAA', 'test', 'test');
        $this->assertTrue($consumer->isCachedResponse());
    }

    public function testFindByVRMWithCacheTrueAndFailoverEnabled()
    {
        $client = $this->getClient();

        $cache = $this->getMock('Carweb\Cache\CacheInterface', array('has','get','save','clear'));
        $cache
            ->expects($this->once())
            ->method('has')
            ->with('strB2BGetVehicleByVRM.AA01AAA')
            ->will($this->returnValue(true));

        $cache
            ->expects($this->once())
            ->method('get')
            ->with('strB2BGetVehicleByVRM.AA01AAA')
            ->will($this->returnValue('<?xml version="1.0" encoding="utf-8"?><GetVehicles></GetVehicles>'));

        $consumer = new Consumer($client, 'username', 'password', 'key', '0.31.1', $cache, true, true);

        $consumer->findByVRM('AA01 AAA', 'test', 'test');
        $this->assertTrue($consumer->isCachedResponse());
    }

    public function testFindByVINFailoverDisabled()
    {
        $client = $this->getClient();
        $response = $this->getResponse(200, '<?xml version="1.0" encoding="utf-8"?><GetVehicles></GetVehicles>');

        $client
            ->expects($this->once())
            ->method('call')
            ->will($this->returnValue($response));

        $consumer = new Consumer($client, 'username', 'password', 'key', '0.31.1', null, true, false);

        $consumer->findByVIN('VIN1234567890', 'test', 'test');
        $this->assertFalse($consumer->isCachedResponse());
    }

    public function testFindByVINFailoverEnabled()
    {
        $client = $this->getClient();
        $response = $this->getResponse(200, '<?xml version="1.0" encoding="utf-8"?><GetVehicles></GetVehicles>');

        $client
            ->expects($this->once())
            ->method('call')
            ->will($this->returnValue($response));

        $consumer = new Consumer($client, 'username', 'password', 'key', '0.31.1', null, true, true);

        $consumer->findByVIN('VIN1234567890', 'test', 'test');
        $this->assertFalse($consumer->isCachedResponse());
    }

    public function testFindByVINWithCacheFalseAndFailoverDisabled()
    {
        $client = $this->getClient();
        $response = $this->getResponse(200, '<?xml version="1.0" encoding="utf-8"?><GetVehicles></GetVehicles>');

        $client
            ->expects($this->once())
            ->method('call')
            ->will($this->returnValue($response));

        $cache = $this->getMock('Carweb\Cache\CacheInterface', array('has','get','save','clear'));
        $cache
            ->expects($this->once())
            ->method('has')
            ->with('strB2BGetVehicleByVIN.VIN1234567890')
            ->will($this->returnValue(false));

        $cache
            ->expects($this->once())
            ->method('save')
            ->with('strB2BGetVehicleByVIN.VIN1234567890','<?xml version="1.0" encoding="utf-8"?><GetVehicles></GetVehicles>');

        $consumer = new Consumer($client, 'username', 'password', 'key', '0.31.1', $cache, true, false);

        $consumer->findByVIN('VIN1234567890', 'test', 'test');
        $this->assertFalse($consumer->isCachedResponse());
    }

    public function testFindByVINWithCacheFalseAndFailoverEnabled()
    {
        $client = $this->getClient();
        $response = $this->getResponse(200, '<?xml version="1.0" encoding="utf-8"?><GetVehicles></GetVehicles>');

        $client
            ->expects($this->once())
            ->method('call')
            ->will($this->returnValue($response));

        $cache = $this->getMock('Carweb\Cache\CacheInterface', array('has','get','save','clear'));
        $cache
            ->expects($this->once())
            ->method('has')
            ->with('strB2BGetVehicleByVIN.VIN1234567890')
            ->will($this->returnValue(false));

        $cache
            ->expects($this->once())
            ->method('save')
            ->with('strB2BGetVehicleByVIN.VIN1234567890','<?xml version="1.0" encoding="utf-8"?><GetVehicles></GetVehicles>');

        $consumer = new Consumer($client, 'username', 'password', 'key', '0.31.1', $cache, true, true);

        $consumer->findByVIN('VIN1234567890', 'test', 'test');
        $this->assertFalse($consumer->isCachedResponse());
    }

    public function testFindByVINWithCacheTrueAndFailoverDisabled()
    {
        $client = $this->getClient();

        $cache = $this->getMock('Carweb\Cache\CacheInterface', array('has','get','save','clear'));
        $cache
            ->expects($this->once())
            ->method('has')
            ->with('strB2BGetVehicleByVIN.VIN1234567890')
            ->will($this->returnValue(true));

        $cache
            ->expects($this->once())
            ->method('get')
            ->with('strB2BGetVehicleByVIN.VIN1234567890')
            ->will($this->returnValue('<?xml version="1.0" encoding="utf-8"?><GetVehicles></GetVehicles>'));

        $consumer = new Consumer($client, 'username', 'password', 'key', '0.31.1', $cache, true, false);

        $consumer->findByVIN('VIN1234567890', 'test', 'test');
        $this->assertTrue($consumer->isCachedResponse());
    }

    public function testFindByVINWithCacheTrueAndFailoverEnabled()
    {
        $client = $this->getClient();

        $cache = $this->getMock('Carweb\Cache\CacheInterface', array('has','get','save','clear'));
        $cache
            ->expects($this->once())
            ->method('has')
            ->with('strB2BGetVehicleByVIN.VIN1234567890')
            ->will($this->returnValue(true));

        $cache
            ->expects($this->once())
            ->method('get')
            ->with('strB2BGetVehicleByVIN.VIN1234567890')
            ->will($this->returnValue('<?xml version="1.0" encoding="utf-8"?><GetVehicles></GetVehicles>'));

        $consumer = new Consumer($client, 'username', 'password', 'key', '0.31.1', $cache, true, true);

        $consumer->findByVIN('VIN1234567890', 'test', 'test');
        $this->assertTrue($consumer->isCachedResponse());
    }

    /**
     * @expectedException \Carweb\Exception\ApiException
     */
    public function testCallWithErrorFailoverDisabled()
    {
        $client = $this->getClient();
        $response = $this->getResponse(200,
'<?xml version="1.0" encoding="utf-8"?>
<VRRError>
    <DataArea>
        <Error>
            <Details>
                <ErrorDescription>Test message</ErrorDescription>
                <ErrorCode>123</ErrorCode>
            </Details>
        </Error>
    </DataArea>
</VRRError>');

        $client
            ->expects($this->once())
            ->method('call')
            ->will($this->returnValue($response));

        $consumer = new Consumer($client, 'username', 'password', 'key', '0.31.1', null, true, false);

        $consumer->findByVIN('VIN1234567890', 'test', 'test');
    }

    /**
     * @expectedException \Carweb\Exception\ApiException
     */
    public function testCallWithErrorFailoverEnabled()
    {
        $client = $this->getClient();
        $response = $this->getResponse(200,
            '<?xml version="1.0" encoding="utf-8"?>
<VRRError>
    <DataArea>
        <Error>
            <Details>
                <ErrorDescription>Test message</ErrorDescription>
                <ErrorCode>123</ErrorCode>
            </Details>
        </Error>
    </DataArea>
</VRRError>');

        $client
            ->expects($this->once())
            ->method('call')
            ->will($this->returnValue($response));

        $consumer = new Consumer($client, 'username', 'password', 'key', '0.31.1', null, true, true);

        $consumer->findByVIN('VIN1234567890', 'test', 'test');
    }

    /**
     * @expectedException \Carweb\Exception\ApiException
     */
    public function testCallWithServerErrorFailoverDisabled()
    {
        $client = $this->getClient();
        $response = $this->getResponse(500,'Server Error');

        $client
            ->expects($this->once())
            ->method('call')
            ->will($this->returnValue($response));

        $consumer = new Consumer($client, 'username', 'password', 'key', '0.31.1', null, true, false);

        $consumer->findByVIN('VIN1234567890', 'test', 'test');
    }

    /**
     * @expectedException \Carweb\Exception\ApiException
     */
    public function testCallWithServerErrorFailoverEnabled()
    {
        $client = $this->getClient();
        $response = $this->getResponse(500,'Server Error', 3);

        $client
            ->expects($this->exactly(3))
            ->method('call')
            ->will($this->returnValue($response));

        $consumer = new Consumer($client, 'username', 'password', 'key', '0.31.1', null, true, true);

        $consumer->findByVIN('VIN1234567890', 'test', 'test');
    }

    private function getClient()
    {
        return $this->getMock('Buzz\Browser', array('call', 'get'));
    }

    private function getResponse($code, $content = null, $expectedNoOfCalls = 1)
    {
        $response = $this->getMock('Buzz\Message\Response', array('isSuccessful', 'getContent'));

        $response
            ->expects($this->exactly($expectedNoOfCalls))
            ->method('isSuccessful')
            ->will($this->returnValue($code == 200));

        $response
            ->expects($this->any())
            ->method('getContent')
            ->will($this->returnValue($content));

        return $response;
    }
}