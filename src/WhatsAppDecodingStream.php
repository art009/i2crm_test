<?php
/**
 * Декодирование потока
 *
 */
declare(strict_types=1);

namespace art009\I2crmTest;

use GuzzleHttp\Psr7\LimitStream;
use GuzzleHttp\Psr7\StreamDecoratorTrait;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\StreamInterface;
use GuzzleHttp\Psr7;

class WhatsAppDecodingStream implements StreamInterface
{
	const BLOCK_SIZE = 4; // 32 bits

	use StreamDecoratorTrait;

	/**
	 * @var string
	 */
	private $buffer = '';

	private $mediaKey;
	private $mediaKeyExpanded;

	const TYPE_CONTENT_IMAGE = 'IMAGE';
	const TYPE_CONTENT_VIDEO = 'VIDEO';
	const TYPE_CONTENT_AUDIO = 'AUDIO';
	const TYPE_CONTENT_DOCUMENT = 'DOCUMENT';

	public $type_salt = [
		self::TYPE_CONTENT_IMAGE => 'WhatsApp Image Keys',
		self::TYPE_CONTENT_VIDEO => 'WhatsApp Video Keys',
		self::TYPE_CONTENT_AUDIO => 'WhatsApp Audio Keys',
		self::TYPE_CONTENT_DOCUMENT => 'WhatsApp Document Keys',
	];

	// данные из ключа
	private $iv;
	private $cipherKey;
	private $macKey;
	private $refKey;

	// данные по зашифроннаму файлу
	private $cipherText;
	private $mac;

	/**
	 * @var StreamInterface
	 */
	private $stream;

	public function __construct(
		string $mediaKey,
		string $media_type,
		StreamInterface $stream
	)
	{
		$this->stream = $stream;
//		1. Obtain `mediaKey`.
		$this->mediaKey = $mediaKey;
//		$this->mediaKeyEncode = base64_decode( $mediaKey);
//      2. Expand it to 112 bytes using HKDF with SHA-256 and type-specific application info (see below). Call this value `mediaKeyExpanded`.
		$this->mediaKeyExpanded = hash_hkdf('sha256', $this->mediaKey, 112, $this->type_salt[$media_type]);
		/**
		 * 3. Split `mediaKeyExpanded` into:
			- `iv`: `mediaKeyExpanded[:16]`
			- `cipherKey`: `mediaKeyExpanded[16:48]`
			- `macKey`: `mediaKeyExpanded[48:80]`
			- `refKey`: `mediaKeyExpanded[80:]` (not used)
		 */
		$this->iv = substr( $this->mediaKeyExpanded ,0 ,16);
		$this->cipherKey = substr( $this->mediaKeyExpanded ,16 ,32);
		$this->macKey = substr( $this->mediaKeyExpanded ,48 ,32);
		$this->refKey = substr( $this->mediaKeyExpanded ,80 );

		// Obtain encrypted media data and split it into:
//		    - `file`: `mediaData[:-10]`
//	        - `mac`: `mediaData[-10:]`
		$plainTextSize = $this->stream->getSize();
		$this->stream->seek(0);
		$this->cipherText = $this->stream->read($plainTextSize - 10);
		$this->stream->seek($plainTextSize - 10);
		$this->mac = $this->stream->read(10);

//		$part_read = $this->cipherText . $this->macKey;
//		$this->stream->seek(0);
//		$full_read = $this->stream->read($plainTextSize);
//		$valid_read = ($part_read == $full_read);

		if (!$this->validate())
			throw new \RuntimeException("Not valide media data by signing `iv + file` with `macKey` using SHA-256.");

	}

	/**
	 * Validate media data with HMAC by signing `iv + file` with `macKey` using SHA-256.
	 * Take in mind that `mac` is truncated to 10 bytes, so you should compare only the first 10 bytes.
	 * @return bool
	 */
	private function validate()
	{
		$hash = substr( hash_hmac('sha256', $this->iv . $this->cipherText, $this->macKey, true) , 0,10);
		$macKey = $this->mac;
		return ($hash == $macKey);
//		return true;
	}

	public function getSize(): ?int
	{
		return $this->stream->getSize();
	}

	/**
	 * Decrypt `file` with AES-CBC using `cipherKey` and `iv`, and unpad it to obtain the result.
	 * @return StreamInterface
	 */
	public function createStream(): StreamInterface
	{
		$plaintext = openssl_decrypt(
			(string) $this->cipherText, // данные для расшифровки
			'aes-256-cbc', // метод шифрования.
			$this->cipherKey, // Ключ
			OPENSSL_RAW_DATA, // можно задать одной из констант: OPENSSL_RAW_DATA, OPENSSL_ZERO_PADDING.
			$this->iv // ненулевой инициализирующий вектор.
		);

		if ($plaintext === false) {
			throw new \RuntimeException("Unable to decrypt data with an initialization vector"
				. " of {$this->iv} using the aes-128-cbc algorithm. Please"
				. " ensure you have provided a valid key size, initialization vector, and key.");
		}

		return Utils::streamFor( $plaintext );
	}

	public function isWritable(): bool
	{
		return false;
	}
}