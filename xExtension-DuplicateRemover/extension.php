<?php

/**
 * DuplicateRemover extension for FreshRSS.
 */
class DuplicateRemoverExtension extends Minz_Extension {
    private $dedupe_mode = 'title';
    private $enable_log = false;

    public function init() {
        $this->loadConfiguration();
        $this->registerHook('entry_before_add', array($this, 'checkDuplicate'));
        $this->registerHook('freshrss_user_maintenance', array($this, 'cleanDuplicates'));

        if ($this->enable_log) {
            error_log('DuplicateRemover: Extension initialized, mode=' . $this->dedupe_mode);
        }
    }

    /**
     * Load user configuration.
     */
    private function loadConfiguration() {
        $mode = $this->getUserConfigurationValue('mode');
        $this->dedupe_mode = in_array($mode, array('title', 'title_link'), true) ? $mode : 'title';

        $log = $this->getUserConfigurationValue('enable_log');
        $this->enable_log = ($log === true || $log === '1' || $log === 1);
    }

    /**
     * Check whether an entry duplicates one already in the database.
     *
     * Returning null cancels insertion of the new article.
     *
     * @param FreshRSS_Entry $entry Entry about to be inserted.
     * @return FreshRSS_Entry|null
     */
    public function checkDuplicate($entry) {
        try {
            $title = $entry->title();
            if (empty($title)) {
                return $entry;
            }

            $link = $entry->link(true);
            if ($this->dedupe_mode === 'title_link' && empty($link)) {
                return $entry;
            }

            $modelPdo = new Minz_ModelPdo();

            if ($this->dedupe_mode === 'title_link') {
                $sql = 'SELECT 1 FROM `_entry` WHERE title = :title AND link = :link LIMIT 1';
                $values = array(':title' => $title, ':link' => $link);
            } else {
                $sql = 'SELECT 1 FROM `_entry` WHERE title = :title LIMIT 1';
                $values = array(':title' => $title);
            }

            $rows = $modelPdo->fetchAssoc($sql, $values);
            if (!empty($rows)) {
                if ($this->enable_log) {
                    $mode_str = $this->dedupe_mode === 'title_link' ? 'title+link' : 'title';
                    error_log("DuplicateRemover: Skipped duplicate - title: {$title}, mode: {$mode_str}");
                }
                return null;
            }
        } catch (Exception $e) {
            error_log('DuplicateRemover: Error checking duplicate - ' . $e->getMessage());
        } catch (Error $e) {
            error_log('DuplicateRemover: Error checking duplicate - ' . $e->getMessage());
        }

        return $entry;
    }

    /**
     * Mark duplicate articles that are already in the database as read,
     * keeping the earliest copy unread.
     */
    public function cleanDuplicates() {
        try {
            $modelPdo = new Minz_ModelPdo();

            if ($this->dedupe_mode === 'title_link') {
                $groups = $modelPdo->fetchAssoc(
                    'SELECT title, link FROM `_entry` GROUP BY title, link HAVING COUNT(*) > 1'
                );
            } else {
                $groups = $modelPdo->fetchAssoc(
                    'SELECT title FROM `_entry` GROUP BY title HAVING COUNT(*) > 1'
                );
            }

            if (empty($groups)) {
                return;
            }

            $idsToMarkRead = array();

            foreach ($groups as $group) {
                $title = isset($group['title']) ? $group['title'] : '';
                if ($title === '') {
                    continue;
                }

                if ($this->dedupe_mode === 'title_link') {
                    $link = isset($group['link']) ? $group['link'] : '';
                    $rows = $modelPdo->fetchAssoc(
                        'SELECT id FROM `_entry` WHERE title = :title AND link = :link ORDER BY date ASC, id ASC',
                        array(':title' => $title, ':link' => $link)
                    );
                } else {
                    $rows = $modelPdo->fetchAssoc(
                        'SELECT id FROM `_entry` WHERE title = :title ORDER BY date ASC, id ASC',
                        array(':title' => $title)
                    );
                }

                if (empty($rows) || count($rows) < 2) {
                    continue;
                }

                $ids = array();
                foreach ($rows as $row) {
                    if (!empty($row['id'])) {
                        $ids[] = $row['id'];
                    }
                }

                if (count($ids) < 2) {
                    continue;
                }

                array_shift($ids);
                $idsToMarkRead = array_merge($idsToMarkRead, $ids);
            }

            if (!empty($idsToMarkRead)) {
                $entryDAO = FreshRSS_Factory::createEntryDao();
                $entryDAO->markRead($idsToMarkRead, true);

                if ($this->enable_log) {
                    error_log('DuplicateRemover: Marked ' . count($idsToMarkRead) . ' existing duplicate article(s) as read');
                }
            }
        } catch (Exception $e) {
            error_log('DuplicateRemover: Error cleaning duplicates - ' . $e->getMessage());
        } catch (Error $e) {
            error_log('DuplicateRemover: Error cleaning duplicates - ' . $e->getMessage());
        }
    }

    public function handleConfigureAction() {
        if (Minz_Request::isPost()) {
            $mode = Minz_Request::param('dedupe_mode', 'title');
            if (!in_array($mode, array('title', 'title_link'), true)) {
                $mode = 'title';
            }

            $this->dedupe_mode = $mode;
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
