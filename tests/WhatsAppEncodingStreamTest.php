<?php
namespace art009\Tests\I2crmTest;

use GuzzleHttp\Psr7\UploadedFile;
use GuzzleHttp\Psr7\Utils;
use PHPUnit\Framework\TestCase;
use art009\I2crmTest\WhatsAppEncodingStream;
use Faker;

class WhatsAppEncodingStreamTest extends TestCase
{
	/**
	 * @dataProvider dataProvider
	 * @param string $encrypted
	 * @param string $key
	 * @param string $original
	 * @param string $save_to
	 * @param string $media_type
	 * @param string|null $sidecar
	 */
	public function testWhatsAppEncodingStream_validateFile(
		string $encrypted,
		string $key,
		string $original,
		string $save_to,
		string $media_type,
		?string $sidecar = NULL
	): void
	{
		$mediaKey = file_get_contents($key);// $this->keyGenerate();
//		file_put_contents($key,$mediaKey);// сохраним ключ в рабочей директории
		$file = fopen($original, 'r');
		$stream = Utils::streamFor($file);
		$encodingStream = new WhatsAppEncodingStream($stream, $mediaKey, $media_type);
		$content = $encodingStream->createStream();

		$upload = new UploadedFile($content, $encodingStream->getSize(), UPLOAD_ERR_OK);
		$upload->moveTo( $save_to );

		$this->assertSame(hash_file('md5', $save_to), hash_file('md5', $encrypted));
	}

	public function dataProvider() : array
	{
		$path = __DIR__ . DIRECTORY_SEPARATOR . 'samples' . DIRECTORY_SEPARATOR;
		$path_work = __DIR__ . DIRECTORY_SEPARATOR . 'work' . DIRECTORY_SEPARATOR;
		return [
			'jpeg_image' => [
				$path . 'IMAGE_2.encrypted',
				$path . 'IMAGE_2.key',
				$path . 'IMAGE_2.original',
				$path_work . 'IMAGE_2.original',
				WhatsAppEncodingStream::TYPE_CONTENT_IMAGE
			],
			'image' => [
				$path . 'IMAGE.encrypted',
				$path . 'IMAGE.key',
				$path . 'IMAGE.original',
				$path_work . 'IMAGE.encrypted',
				WhatsAppEncodingStream::TYPE_CONTENT_IMAGE,
			],
			'audio' => [
				$path . 'AUDIO.encrypted',
				$path . 'AUDIO.key',
				$path . 'AUDIO.original',
				$path_work . 'AUDIO.encrypted',
				WhatsAppEncodingStream::TYPE_CONTENT_AUDIO,
			],
			'video' => [
				$path . 'VIDEO.encrypted',
				$path . 'VIDEO.key',
				$path . 'VIDEO.original',
				$path_work . 'VIDEO.encrypted',
				WhatsAppEncodingStream::TYPE_CONTENT_VIDEO,
				$path . 'VIDEO.sidecar',
			],
		];
	}

	private function keyGenerate()
	{
		$faker = Faker\Factory::create();
		return $faker->regexify('[A-Za-z0-9]{32}');
	}
}