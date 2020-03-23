<?php
declare(strict_types=1);

namespace Belvg\Sqs\Setup;

use Magento\Framework\Setup\InstallDataInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;

use Magento\Framework\App\Config\ConfigResource\ConfigInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\MessageQueue\Topology\ConfigInterface as TopologyConfig;

use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\Store;
use Belvg\Sqs\Model\Config;

/**
 * RecurringData class to search for queues names and fill/update a system config serialized array
 */
class RecurringData implements InstallDataInterface
{
    /**
     * @var ConfigInterface
     */
    private $resourceConfig;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;
    
    /**
     * @var SerializerInterface
     */
    private $serializer;
    
    /**
     * @var TopologyConfig
     */
    private $topologyConfig;

    /**
     * Constructor
     *
     * @param ConfigInterface $resourceConfig
     * @param ScopeConfigInterface $scopeConfig
     * @param SerializerInterface $serializer
     * @param TopologyConfig $topologyConfig
     */
    public function __construct(
        ConfigInterface $resourceConfig,
        ScopeConfigInterface $scopeConfig,
        SerializerInterface $serializer,
        TopologyConfig $topologyConfig
    ){
        $this->resourceConfig = $resourceConfig;
        $this->scopeConfig = $scopeConfig;
        $this->serializer = $serializer;
        $this->topologyConfig = $topologyConfig;
    }

    /**
     * Save config value with default scope
     * 
     * @param string $path
     * @param string $value
     */
    private function saveConfig($path, $value):void
    {
        $this->resourceConfig->saveConfig($path, $value, ScopeConfigInterface::SCOPE_TYPE_DEFAULT, Store::DEFAULT_STORE_ID);
    }

    /**
     * Return a list of queue names available for the connection
     *
     * @param string $connection
     * @return array List of queue names
     */
    private function getQueuesListByConnection($connection)
    {
        $queuesList = [];
        $queues = $this->topologyConfig->getQueues();

        // Add to the list all queues with the provided connection
        foreach ($queues as $queue) {
            if ($queue->getConnection() === $connection) {
                $queuesList[] = $queue->getName();
            }
        }
        return $queuesList;
    }

    /**
     * {@inheritdoc}
     */
    public function install(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        // Get XML actually declared queues names
        $queuesList = $this->getQueuesListByConnection(Config::SQS_CONFIG);

        // Get the system config queues names serialized array
        if (!empty($this->scopeConfig->getValue(Config::XML_PATH_SQS_QUEUE_NAME, ScopeInterface::SCOPE_STORE))) {
            $sysConfQueuesNames = $this->serializer->unserialize($this->scopeConfig->getValue(Config::XML_PATH_SQS_QUEUE_NAME, ScopeInterface::SCOPE_STORE));
        } else {
            $sysConfQueuesNames = [];
        }
        
        // Remove old queues from system config serialized array
        foreach ($sysConfQueuesNames as $sysConfQueueName => $sysConfQueueSqsName) {
            if (!in_array($sysConfQueueName, $queuesList)) {
                unset($sysConfQueuesNames[$sysConfQueueName]);
            }
        }

        // Add new queues to system config serialized array
        foreach ($queuesList as $queueName) {
            if (!isset($sysConfQueuesNames[$queueName])) {
                $sysConfQueuesNames[$queueName] = '';
            }
        }

        // Serialize the new array
        $newSysConfQueuesNames = $this->serializer->serialize($sysConfQueuesNames);

        // Save the new system config array
        $this->saveConfig(Config::XML_PATH_SQS_QUEUE_NAME,$newSysConfQueuesNames);
    }
}