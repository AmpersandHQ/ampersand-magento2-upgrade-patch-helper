<?php
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