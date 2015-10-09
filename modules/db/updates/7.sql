alter table core_versions add column version_str varchar(50);
alter table core_update_history add column version_str varchar(50);

update core_versions set version_str=concat('1.0.', version);
update core_update_history set version_str=concat('1.0.', version);