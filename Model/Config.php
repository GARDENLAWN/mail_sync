<?php
declare(strict_types=1);

namespace GardenLawn\MailSync\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    // Native Magento Paths
    private const XML_PATH_TRANS_EMAIL = 'trans_email/ident_general/email';
    private const XML_PATH_TRANS_NAME = 'trans_email/ident_general/name';
    // Note: Native Magento doesn't always have system/smtp/host exposed in UI without modules,
    // but we will use standard keys often used by SMTP modules or core if available.
    // If you use a specific SMTP module, these might need adjustment.
    // Assuming standard keys or that you will populate them.
    private const XML_PATH_CORE_SMTP_HOST = 'system/smtp/host';
    private const XML_PATH_CORE_SMTP_PORT = 'system/smtp/port';

    // Module Paths
    private const XML_PATH_PASSWORD = 'gardenlawn_mailsync/general/password';
    private const XML_PATH_IMAP_HOST = 'gardenlawn_mailsync/general/imap_settings/host';
    private const XML_PATH_IMAP_PORT = 'gardenlawn_mailsync/general/imap_settings/port';
    private const XML_PATH_IMAP_ENCRYPTION = 'gardenlawn_mailsync/general/imap_settings/encryption';
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

        $username = trim((string)$this->scopeConfig->getValue(self::XML_PATH_TRANS_EMAIL, $scopeType, $scopeCode));
        $senderName = trim((string)$this->scopeConfig->getValue(self::XML_PATH_TRANS_NAME, $scopeType, $scopeCode));

        $password = trim((string)$this->scopeConfig->getValue(self::XML_PATH_PASSWORD, $scopeType, $scopeCode));
        if ($password) {
             $password = $this->encryptor->decrypt($password);
        }

        $smtpHost = trim((string)$this->scopeConfig->getValue(self::XML_PATH_CORE_SMTP_HOST, $scopeType, $scopeCode));
        $smtpPort = (int)$this->scopeConfig->getValue(self::XML_PATH_CORE_SMTP_PORT, $scopeType, $scopeCode);

        // Fallback for SMTP if not set in system config, maybe try to guess or leave empty
        // If empty, Transport might fail, which is expected if not configured.

        return new Account(
            username: $username,
            password: $password,
            senderName: $senderName ?: $username,
            imapHost: trim((string)$this->scopeConfig->getValue(self::XML_PATH_IMAP_HOST, $scopeType, $scopeCode)),
            imapPort: (int)$this->scopeConfig->getValue(self::XML_PATH_IMAP_PORT, $scopeType, $scopeCode),
            imapEncryption: (string)$this->scopeConfig->getValue(self::XML_PATH_IMAP_ENCRYPTION, $scopeType, $scopeCode),
            smtpHost: $smtpHost,
            smtpPort: $smtpPort,
            smtpEncryption: (string)$this->scopeConfig->getValue(self::XML_PATH_SMTP_ENCRYPTION, $scopeType, $scopeCode)
        );
    }
}
