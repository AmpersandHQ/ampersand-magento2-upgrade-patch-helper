<?php
if (!class_exists('Magento\AdobeIms\Model\NewBusinessLogic')) {
    require_once  __DIR__ . '/vendor/magento/module-adobe-ims/Model/NewBusinessLogic.php';
}

if (!class_exists('Some_Plugin_Class', false)) {
    class Some_Plugin_Class
    {
        public function beforeGetUpdatedAt($subject)
        {
            // do stuff
        }

        public function afterGetUpdatedAt($subject, $result)
        {
            return $result;
        }

        public function aroundGetUpdatedAt($subject, callable $proceed)
        {
            return $proceed();
        }
    }
}

if (!class_exists('Some_Plugin_Class_On_Delete_Target', false)) {
    class Some_Plugin_Class_On_Delete_Target
    {
        public function beforeGetUpdatedAt($subject)
        {
            // do stuff
        }

        public function afterGetUpdatedAt($subject, $result)
        {
            return $result;
        }

        public function aroundGetUpdatedAt($subject, callable $proceed)
        {
            return $proceed();
        }

        public function notRelevant()
        {
            return false;
        }
    }
}

if (!class_exists('Some_Plugin_Class_On_Created_Target', false)) {
    class Some_Plugin_Class_On_Created_Target
    {
        public function beforeGetUpdatedAt($subject)
        {
            // do stuff
        }

        public function beforeNothing($subject)
        {
            // do stuff
        }

        public function afterGetUpdatedAt($subject, $result)
        {
            return $result;
        }

        public function aroundGetUpdatedAt($subject, callable $proceed)
        {
            return $proceed();
        }

        public function notRelevant()
        {
            return false;
        }
    }
}

if (!class_exists('Some_Admin_Plugin_Class', false)) {
    class Some_Admin_Plugin_Class
    {
        public function beforeGetUpdatedAt($subject)
        {
            // do stuff
        }

        public function afterGetUpdatedAt($subject, $result)
        {
            return $result;
        }

        public function aroundGetUpdatedAt($subject, callable $proceed)
        {
            return $proceed();
        }
    }
}