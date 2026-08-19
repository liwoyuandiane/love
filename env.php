<?php
/**
 * .env 路径解析（v3.2.0 新增）
 *
 * 统一规则：
 *  - Docker 部署：数据目录 DATA_DIR（默认 /data，对应宿主机挂载的 love/ 目录）下的 .env
 *    → 安装向导生成的 .env 直接落在宿主机 love/ 目录，容器重建配置不丢失
 *  - 传统部署（Apache/Nginx/1Panel，无 /data 目录）：回退到应用根目录 .env
 */

function resolveEnvFile(): string {
    static $envFile = null;

    if ($envFile !== null) {
        return $envFile;
    }

    $dataDir = (string)getenv('DATA_DIR');
    if ($dataDir === '' && is_dir('/data')) {
        // 未显式设置 DATA_DIR 但容器存在 /data 挂载（docker run 新一键命令）
        $dataDir = '/data';
    }

    if ($dataDir !== '' && is_dir($dataDir)) {
        $envFile = rtrim($dataDir, '/') . '/.env';
    } else {
        $envFile = __DIR__ . '/.env';
    }

    return $envFile;
}
