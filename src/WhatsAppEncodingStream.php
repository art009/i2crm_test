<?php
/**
 * Кодирование потока
 *
 */
namespace art009\I2crmTest;

use GuzzleHttp\Psr7\StreamDecoratorTrait;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\StreamInterface;
use art009\I2crmTest\ITypeContent;
use GuzzleHttp\Psr7;

class WhatsAppEncodingStream implements StreamInterface, ITypeContent
{
	use StreamDecoratorTrait;
	use WhatsAppTrait;

	private $mediaKey;
	private $plaintext;

	private $mediaKeyExpanded;

	private $iv;
	private $cipherKey;
	private $macKey;
	private $refKey;

	public function __construct(
		StreamInterface $plaintext,
		string $mediaKey,
		string $media_type
	) {
		$this->plaintext = $plaintext;
		$this->mediaKey = $mediaKey;

		$this->mediaKeyExpanded = hash_hkdf('sha256', $this->mediaKey, 112, $this->getTypesContent()[$media_type]);

		$this->iv = substr( $this->mediaKeyExpanded ,0 ,16);
		$this->cipherKey = substr( $this->mediaKeyExpanded ,16 ,32);
		$this->macKey = substr( $this->mediaKeyExpanded ,48 ,32);
		$this->refKey = substr( $this->mediaKeyExpanded ,80 );
	}

	public function createStream(): StreamInterface
	{
		$cipherText = openssl_encrypt(
			(string) $this->plaintext,
			'aes-256-cbc',
			$this->cipherKey,
			OPENSSL_RAW_DATA,
			$this->iv
		);

		if ($cipherText === false) {
			throw new \RuntimeException("Unable to encrypt data with an initialization vector"
				. " of {$this->iv} using the aes-256-cbc algorithm. Please"
				. " ensure you have provided a valid key size and initialization vector.");
		}

		$mac = substr( hash_hmac('sha256', $this->iv . $cipherText, $this->macKey, true) , 0,10);

		return Utils::streamFor( $cipherText . $mac );
	}

	public function isWritable(): bool
	{
		return false;
	}
}