INSERT INTO `thread_post` (`id`, `threadId`, `content`, `adopted`, `ats`, `userId`, `parentId`, `subposts`, `ups`, `targetType`, `targetId`, `createdTime`) VALUES (1,0,'资讯频道名称和每页资讯显示数，在【管理后台】-【系统】-【运营设置】-【资讯】中修改。',0,'[]',1,0,0,0,'article',1,1434020555),(2,0,'每篇资讯的最下方，都可以点赞和分享哦，分享是一种精神~',0,'[]',1,0,0,0,'article',1,1434020591);
INSERT INTO `upgrade_notice` (`id`, `userId`, `code`, `version`, `createdTime`) VALUES (1,1,'MAIN','7.0.0',1470190293);
INSERT INTO `upload_file_inits` (`id`, `globalId`, `status`, `hashId`, `targetId`, `targetType`, `filename`, `ext`, `fileSize`, `etag`, `length`, `convertHash`, `convertStatus`, `metas`, `metas2`, `type`, `storage`, `convertParams`, `updatedUserId`, `updatedTime`, `createdUserId`, `createdTime`) VALUES (15,'0','ok','courselesson/6/2015812051502-cki2y0.mp4',6,'courselesson','选择在线教育平台还是自己建网校？.mp4','mp4',6124545,'',0,'ch-courselesson/6/2015812051502-cki2y0.mp4','none',NULL,NULL,'video','local',NULL,1,1439370902,1,1439370902);
INSERT INTO `upload_files` (`id`, `globalId`, `hashId`, `targetId`, `targetType`, `useType`, `filename`, `ext`, `fileSize`, `etag`, `length`, `description`, `status`, `convertHash`, `convertStatus`, `convertParams`, `metas`, `metas2`, `type`, `storage`, `isPublic`, `canDownload`, `usedCount`, `updatedUserId`, `updatedTime`, `createdUserId`, `createdTime`) VALUES (1,'0','coursematerial/6/2015525042955-7lbz5n.pptx',6,'coursematerial',NULL,'EduSoho慕课简介.pptx','pptx',2867612,'',0,NULL,'ok','ch-coursematerial/6/2015525042955-7lbz5n.pptx','none',NULL,NULL,NULL,'ppt','local',0,0,0,1,1432542595,1,1432542595),(8,'0','coursematerial/6/2015528090713-7v7zxs.pptx',6,'coursematerial',NULL,'EduSoho教育云服务介绍11.13.pptx','pptx',2698076,'',0,NULL,'ok','ch-coursematerial/6/2015528090713-7v7zxs.pptx','none',NULL,NULL,NULL,'ppt','local',0,0,0,1,1432775233,1,1432775233),(12,'0','coursematerial/4/2015611014223-kfqpft.pdf',4,'coursematerial',NULL,'常用的邮箱服务器SMTP地址、端口.pdf','pdf',118610,'',0,NULL,'ok','ch-coursematerial/4/2015611014223-kfqpft.pdf','none',NULL,NULL,NULL,'document','local',0,0,2,1,1434001343,1,1434001343),(14,'0','courselesson/3/2015812051251-agn8of.mp4',3,'courselesson',NULL,'在线教育云计算技术（EduSoho教育云）.mp4','mp4',8232625,'',0,NULL,'ok','ch-courselesson/3/2015812051251-agn8of.mp4','none',NULL,NULL,NULL,'video','local',0,0,1,1,1439370771,1,1439370771),(15,'0','courselesson/6/2015812051502-cki2y0.mp4',6,'courselesson',NULL,'选择在线教育平台还是自己建网校？.mp4','mp4',6124545,'',0,NULL,'ok','ch-courselesson/6/2015812051502-cki2y0.mp4','none',NULL,NULL,NULL,'video','local',0,0,1,1,1439370902,1,1439370902);
