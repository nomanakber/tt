<?php

namespace TcsCourier\Shipping\Setup;

use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\UpgradeSchemaInterface;

class UpgradeSchema implements UpgradeSchemaInterface
{
    public function upgrade(
        SchemaSetupInterface $setup,
        ModuleContextInterface $context
    ) {
        $installer = $setup;

        $installer->startSetup();
        $installer->getConnection()->addColumn(
            $installer->getTable('TcsCourier_shipping_data'),
            'logistic_type',
            [
                'type' => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                'length' => '2M',
                'nullable' => false,
                'default' => null,
                'comment' => 'Custom Attribute',
            ]
        );
        $installer->endSetup();
    }
}