<?php
    require_once(dirname(__FILE__).'/../boot.php');
    $backupDirectory = SGConfig::get('SG_BACKUP_DIRECTORY');
    $maxUploadSize = ini_get('upload_max_filesize');
?>
<div class="modal-dialog">
    <div class="modal-content">
        <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
            <h4 class="modal-title"><?php _t('Import from')?></h4>
        </div>
        <div class="modal-body sg-modal-body" id="sg-modal-inport-from">
            <div class="col-md-12" id="modal-import-2">
                <div class="form-group">
                    <label class="col-md-2 control-label sg-upload-label" for="textinput"><?php _t('SGBP file')?></label>
                    <div class="col-md-10">
                        <div class="input-group">
                            <span class="input-group-btn">
                                <span class="btn btn-primary btn-file">
                                    <?php _t('Browse')?>&hellip; <input type="file" class="sg-backup-upload-input" name="sgbpFile" accept=".sgbp" data-max-file-size="<?php echo convertToBytes($maxUploadSize.'B'); ?>">
                                </span>
                            </span>
                            <input type="text" class="form-control" readonly>
                        </div>
                        <br/>
                        <span class="help-block">Note: If your file is bigger than <?php echo $maxUploadSize; ?>B, you can copy it inside the following folder and it will be automatically detected: <br/><strong><?php echo realpath($backupDirectory);?></strong></span>
                    </div>
                </div>
            </div>
            <div class="clearfix"></div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-default" data-dismiss="modal"><?php echo _t("Close")?></button>
            <button type="button" data-remote="importBackup" id="uploadSgbpFile" class="btn btn-primary"><?php echo _t('Import')?></button>
        </div>
    </div>
</div>
