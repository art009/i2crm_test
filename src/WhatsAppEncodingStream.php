<?php
/**
 * Кодирование потока
 *
 */
namespace art009\I2crmTest;

use GuzzleHttp\Psr7\StreamDecoratorTrait;
use Psr\Http\Message\StreamInterface;

class WhatsAppEncodingStream implements StreamInterface
{
	use StreamDecoratorTrait;


}