<?php

/**
 * DuplicateRemover Extension for FreshRSS
 * 
 * 自动检测并标记跨订阅源的重复文章
 * 支持基于标题或标题+链接的去重策略
 */
class DuplicateRemoverExtension extends Minz_Extension {
    
    // 去重模式：'title' 仅基于标题，'title_link' 基于标题+链接
    private $dedupe_mode = 'title_link';
    
    // 是否启用日志
    private $enable_log = false;
    
    public function init() {
        // 注册文章插入前的钩子
        $this->registerHook('entry_before_insert', array($this, 'checkDuplicate'));
        
        // 加载配置（不自动保存，避免重置配置）
        $this->loadConfiguration();
        
        if ($this->enable_log) {
            error_log('DuplicateRemover: Extension initialized, mode=' . $this->dedupe_mode);
        }
    }
    
    /**
     * 加载用户配置
     */
    private function loadConfiguration() {
        // 去重模式配置
        if (isset(FreshRSS_Context::$user_conf->DuplicateRemover_mode)) {
            $this->dedupe_mode = FreshRSS_Context::$user_conf->DuplicateRemover_mode;
        } else {
            // 默认使用标题+链接模式
            FreshRSS_Context::$user_conf->DuplicateRemover_mode = 'title_link';
            FreshRSS_Context::$user_conf->save();
        }
        
        // 日志配置（参考 TranslateTitlesCN：保存为字符串 '1'）
        if (isset(FreshRSS_Context::$user_conf->DuplicateRemover_log)) {
            $this->enable_log = (FreshRSS_Context::$user_conf->DuplicateRemover_log == '1' || FreshRSS_Context::$user_conf->DuplicateRemover_log === true);
        } else {
            // 不自动设置默认值，避免重置用户配置
            $this->enable_log = false;
        }
    }
    
    /**
     * 检查重复文章
     * 
     * @param FreshRSS_Entry $entry 待插入的文章对象
     * @return FreshRSS_Entry 处理后的文章对象
     */
    public function checkDuplicate($entry) {
        try {
            $title = $entry->title();
            $link = $entry->link();
            
            if (empty($title)) {
                return $entry;
            }
            
            // 获取数据库连接
            $db = FreshRSS_Context::$system_conf->db;
            $user = FreshRSS_Context::user();
            
            if (empty($user)) {
                // CLI 模式下可能需要手动获取用户
                return $entry;
            }
            
            $table = $db['prefix'] . $user . '_entry';
            
            // 使用 Minz_Pdo 访问数据库
            $pdo = FreshRSS_Context::$system_conf->pdo;
            if (!$pdo) {
                return $entry;
            }
            
            // 构建查询条件（PostgreSQL 使用双引号引用标识符）
            $sql = '';
            $params = array();
            
            // 转义表名（PostgreSQL）
            $quoted_table = '"' . str_replace('"', '""', $table) . '"';
            
            if ($this->dedupe_mode === 'title_link' && !empty($link)) {
                // 基于标题+链接去重
                $sql = "SELECT COUNT(*) as cnt FROM {$quoted_table} WHERE \"title\" = ? AND \"link\" = ?";
                $params = array($title, $link);
            } else {
                // 仅基于标题去重
                $sql = "SELECT COUNT(*) as cnt FROM {$quoted_table} WHERE \"title\" = ?";
                $params = array($title);
            }
            
            // 执行查询
            $stm = $pdo->prepare($sql);
            if ($stm) {
                $stm->execute($params);
                $result = $stm->fetch(PDO::FETCH_ASSOC);
                
                if ($result && isset($result['cnt']) && $result['cnt'] > 0) {
                    // 找到重复文章，标记为已读
                    $entry->_isRead(true);
                    
                    if ($this->enable_log) {
                        $mode_str = $this->dedupe_mode === 'title_link' ? 'title+link' : 'title';
                        error_log("DuplicateRemover: Marked duplicate as read - title: {$title}, mode: {$mode_str}");
                    }
                }
            }
            
        } catch (Exception $e) {
            // 发生错误时记录日志，但不影响文章插入
            error_log('DuplicateRemover: Error checking duplicate - ' . $e->getMessage());
        } catch (Error $e) {
            // PHP 7+ 错误处理
            error_log('DuplicateRemover: Error checking duplicate - ' . $e->getMessage());
        }
        
        return $entry;
    }
    
    /**
     * 处理配置页面（完全参考 TranslateTitlesCN 的方式）
     */
    public function handleConfigureAction() {
        if (Minz_Request::isPost()) {
            // 保存去重模式
            $mode = Minz_Request::param('dedupe_mode', 'title_link');
            if (in_array($mode, array('title', 'title_link'))) {
                FreshRSS_Context::$user_conf->DuplicateRemover_mode = $mode;
                $this->dedupe_mode = $mode;
            }
            
            // 保存日志设置（参考 TranslateTitlesCN 的方式）
            // checkbox 未选中时不会提交参数，选中时提交 "1"
            // 参考 TranslateTitlesCN：保存为字符串 '1' 或空（不保存）
            $log_param = Minz_Request::param('enable_log', '');
            // 如果选中，保存为字符串 '1'，否则保存为 false
            if ($log_param === '1' || $log_param === 1 || $log_param === true) {
                FreshRSS_Context::$user_conf->DuplicateRemover_log = '1';
                $this->enable_log = true;
            } else {
                FreshRSS_Context::$user_conf->DuplicateRemover_log = false;
                $this->enable_log = false;
            }
            
            // 保存配置（参考 TranslateTitlesCN 的方式）
            FreshRSS_Context::$user_conf->save();
        }
    }
    
    /**
     * 处理卸载操作
     */
    public function handleUninstallAction() {
        // 清除配置
        if (isset(FreshRSS_Context::$user_conf->DuplicateRemover_mode)) {
            unset(FreshRSS_Context::$user_conf->DuplicateRemover_mode);
        }
        if (isset(FreshRSS_Context::$user_conf->DuplicateRemover_log)) {
            unset(FreshRSS_Context::$user_conf->DuplicateRemover_log);
        }
        FreshRSS_Context::$user_conf->save();
    }
}

