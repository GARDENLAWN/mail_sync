<?php
declare(strict_types=1);

namespace GardenLawn\MailSync\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    private const XML_PATH_SENDER_NAME = 'gardenlawn_mailsync/general/sender_name';
    private const XML_PATH_USERNAME = 'gardenlawn_mailsync/general/username';
    private const XML_PATH_PASSWORD = 'gardenlawn_mailsync/general/password';

    private const XML_PATH_IMAP_HOST = 'gardenlawn_mailsync/general/imap_settings/host';
    private const XML_PATH_IMAP_PORT = 'gardenlawn_mailsync/general/imap_settings/port';
    private const XML_PATH_IMAP_ENCRYPTION = 'gardenlawn_mailsync/general/imap_settings/encryption';

    private const XML_PATH_SMTP_HOST = 'gardenlawn_mailsync/general/smtp_settings/host';
    private const XML_PATH_SMTP_PORT = 'gardenlawn_mailsync/general/smtp_settings/port';
    private const XML_PATH_SMTP_ENCRYPTION = 'gardenlawn_mailsync/general/smtp_settings/encryption';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly EncryptorInterface $encryptor
    ) {
    }

    public function getAccount(?int $websiteId = null): Account
    {
        $scopeType = ScopeInterface::SCOPE_WEBSITES;
        $scopeCode = $websiteId;

        if ($websiteId === null) {
            $scopeType = ScopeConfigInterface::SCOPE_TYPE_DEFAULT;
            $scopeCode = null;
        }

        $username = trim((string)$this->scopeConfig->getValue(self::XML_PATH_USERNAME, $scopeType, $scopeCode));
        $password = trim((string)$this->scopeConfig->getValue(self::XML_PATH_PASSWORD, $scopeType, $scopeCode));
        $senderName = trim((string)$this->scopeConfig->getValue(self::XML_PATH_SENDER_NAME, $scopeType, $scopeCode));

        if ($password) {
             $password = $this->encryptor->decrypt($password);
        }

        return new Account(
            username: $username,
            password: $password,
            senderName: $senderName ?: $username,
            imapHost: trim((string)$this->scopeConfig->getValue(self::XML_PATH_IMAP_HOST, $scopeType, $scopeCode)),
            imapPort: (int)$this->scopeConfig->getValue(self::XML_PATH_IMAP_PORT, $scopeType, $scopeCode),
            imapEncryption: (string)$this->scopeConfig->getValue(self::XML_PATH_IMAP_ENCRYPTION, $scopeType, $scopeCode),
            smtpHost: trim((string)$this->scopeConfig->getValue(self::XML_PATH_SMTP_HOST, $scopeType, $scopeCode)),
            smtpPort: (int)$this->scopeConfig->getValue(self::XML_PATH_SMTP_PORT, $scopeType, $scopeCode),
            smtpEncryption: (string)$this->scopeConfig->getValue(self::XML_PATH_SMTP_ENCRYPTION, $scopeType, $scopeCode)
        );
    }
}
