<?php

namespace Ampersand\PatchHelper\Checks;

use Ampersand\PatchHelper\Checks;

class QueueConsumerXml extends AbstractCheck
{
    /**
     * @return bool
     */
    public function canCheck()
    {
        return (str_ends_with($this->patchEntry->getPath(), '/etc/queue_consumer.xml'));
    }

    /**
     * Add INFO notices when queue consumers are added / removed / changed
     *
     * @return void
     */
    public function check()
    {
        foreach ($this->patchEntry->getAddedQueueConsumers() as $consumerName) {
            $this->infos[Checks::TYPE_QUEUE_CONSUMER_ADDED][$consumerName] = $consumerName;
        }

        foreach ($this->patchEntry->getRemovedQueueConsumers() as $consumerName) {
            $this->infos[Checks::TYPE_QUEUE_CONSUMER_REMOVED][$consumerName] = $consumerName;
        }

        if (isset($this->infos[Checks::TYPE_QUEUE_CONSUMER_ADDED])) {
            // If the same file has been added and removed within the one file, flag it as a change
            foreach ($this->infos[Checks::TYPE_QUEUE_CONSUMER_ADDED] as $consumerAdded) {
                if (isset($this->infos[Checks::TYPE_QUEUE_CONSUMER_REMOVED][$consumerAdded])) {
                    $this->infos[Checks::TYPE_QUEUE_CONSUMER_CHANGED][$consumerAdded] = $consumerAdded;
                    unset($this->infos[Checks::TYPE_QUEUE_CONSUMER_ADDED][$consumerAdded]);
                    unset($this->infos[Checks::TYPE_QUEUE_CONSUMER_REMOVED][$consumerAdded]);
                }
            }
        }
    }
}
