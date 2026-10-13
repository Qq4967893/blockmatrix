-- ============================================================
-- 环境：new-ginfx (Dev & Live)
-- 数据库：blockmatrix
-- ============================================================
-- ============================================================
-- 日期：2026-08-24
-- 说明：菜单接口关联表优化：menu_url 改为 menu_endpoint，server_url 改为 api_path，删除 web_url
-- ============================================================

ALTER TABLE blockmatrix.`menu_url`
  CHANGE COLUMN `server_url` `api_path` varchar(64) NULL DEFAULT '' COMMENT 'API路径',
  DROP COLUMN `web_url`,
  COMMENT = '菜单接口关联表',
  RENAME TO blockmatrix.`menu_endpoint`;
