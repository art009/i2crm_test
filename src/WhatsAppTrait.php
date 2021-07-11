<?php
namespace art009\I2crmTest;

trait WhatsAppTrait
{
	public static function getTypesContent()
	{
		return [
			static::TYPE_CONTENT_IMAGE => 'WhatsApp Image Keys',
			static::TYPE_CONTENT_VIDEO => 'WhatsApp Video Keys',
			static::TYPE_CONTENT_AUDIO => 'WhatsApp Audio Keys',
			static::TYPE_CONTENT_DOCUMENT => 'WhatsApp Document Keys',
		];
	}
}