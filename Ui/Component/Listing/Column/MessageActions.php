<?php
declare(strict_types=1);

namespace GardenLawn\MailSync\Ui\Component\Listing\Column;

use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;
use Magento\Framework\UrlInterface;

class MessageActions extends Column
{
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly UrlInterface $urlBuilder,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    public function prepareDataSource(array $dataSource): array
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as &$item) {
                if (isset($item['entity_id'])) {
                    $item[$this->getData('name')] = [
                        'reply' => [
                            'href' => $this->urlBuilder->getUrl(
                                'gardenlawn_mailsync/message/reply',
                                ['id' => $item['entity_id']]
                            ),
                            'label' => __('Reply')
                        ]
                    ];
                }
            }
        }

        return $dataSource;
    }
}
