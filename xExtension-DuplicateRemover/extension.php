<?php

/**
 * DuplicateRemover extension for FreshRSS.
 */
class DuplicateRemoverExtension extends Minz_Extension {
    private $dedupe_mode = 'title_link';
    private $enable_log = false;
    
    public function init() {
        $this->registerHook('entry_before_insert', array($this, 'checkDuplicate'));
        $this->loadConfiguration();
        
        if ($this->enable_log) {
            error_log('DuplicateRemover: Extension initialized, mode=' . $this->dedupe_mode);
        }
    }
    
    /**
     * Load user configuration.
     */
    private function loadConfiguration() {
        $mode = $this->getUserConfigurationValue('mode');
        if ($mode === null && isset(FreshRSS_Context::$user_conf->DuplicateRemover_mode)) {
            $mode = FreshRSS_Context::$user_conf->DuplicateRemover_mode;
        }
        $this->dedupe_mode = in_array($mode, array('title', 'title_link'), true) ? $mode : 'title_link';

        $log = $this->getUserConfigurationValue('enable_log');
        if ($log === null && isset(FreshRSS_Context::$user_conf->DuplicateRemover_log)) {
            $log = FreshRSS_Context::$user_conf->DuplicateRemover_log;
        }
        $this->enable_log = ($log === true || $log === '1' || $log === 1);
    }
    
    /**
     * Check whether an entry already exists.
     *
     * @param FreshRSS_Entry $entry Entry about to be inserted.
     * @return FreshRSS_Entry Processed entry.
     */
    public function checkDuplicate($entry) {
        try {
            $title = $entry->title();
            $link = $entry->link();
            
            if (empty($title)) {
                return $entry;
            }
            
            $db = FreshRSS_Context::$system_conf->db;
            $user = FreshRSS_Context::user();
            
            if (empty($user)) {
                return $entry;
            }
            
            $table = $db['prefix'] . $user . '_entry';
            
            $pdo = FreshRSS_Context::$system_conf->pdo;
            if (!$pdo) {
                return $entry;
            }
            
            $sql = '';
            $params = array();

            if ($this->dedupe_mode === 'title_link' && !empty($link)) {
                $sql = "SELECT 1 FROM {$table} WHERE title = ? AND link = ? LIMIT 1";
                $params = array($title, $link);
            } else {
                $sql = "SELECT 1 FROM {$table} WHERE title = ? LIMIT 1";
                $params = array($title);
            }

            $stm = $pdo->prepare($sql);
            if ($stm) {
                $stm->execute($params);
                if ($stm->fetch(PDO::FETCH_ASSOC)) {
                    $entry->_isRead(true);
                    
                    if ($this->enable_log) {
                        $mode_str = $this->dedupe_mode === 'title_link' ? 'title+link' : 'title';
                        error_log("DuplicateRemover: Marked duplicate as read - title: {$title}, mode: {$mode_str}");
                    }
                }
            }
            
        } catch (Exception $e) {
            error_log('DuplicateRemover: Error checking duplicate - ' . $e->getMessage());
        } catch (Error $e) {
            error_log('DuplicateRemover: Error checking duplicate - ' . $e->getMessage());
        }
        
        return $entry;
    }
    
    public function handleConfigureAction() {
        if (Minz_Request::isPost()) {
            $mode = Minz_Request::param('dedupe_mode', 'title_link');
            if (in_array($mode, array('title', 'title_link'), true)) {
                $this->dedupe_mode = $mode;
            } else {
                $mode = 'title_link';
            }

            $this->enable_log = Minz_Request::param('enable_log', '') === '1';
            $this->setUserConfiguration(array(
                'mode' => $mode,
                'enable_log' => $this->enable_log,
            ));
        }
    }
    
    /**
     * Remove extension configuration.
     */
    public function handleUninstallAction() {
        $this->setUserConfiguration(array());
    }
}

