sgBackup = {};
sgBackup.isModalOpen = false;
SG_CURRENT_ACTIVE_AJAX = '';
SG_NOTICE_EXECUTION_TIMEOUT = 'timeoutError'

jQuery(window).load(function() {
    sgBackup.showReviewModal();
});

jQuery(document).ready( function() {
    sgBackup.init();
    if(typeof SG_AJAX_REQUEST_FREQUENCY === 'undefined'){
        SG_AJAX_REQUEST_FREQUENCY = 2000;
    }

    sgBackup.hideAjaxSpinner();

    jQuery('.notice-dismiss').on('click', function() {
        if(jQuery(this).parent().attr('which-notice') == SG_NOTICE_EXECUTION_TIMEOUT) {
            var sgNoticeClosedHandler = new sgRequestHandler('hideNotice', {notice: SG_NOTICE_EXECUTION_TIMEOUT});
            sgNoticeClosedHandler.run();
        }
    });
});

//SG init
sgBackup.init = function(){
    sgBackup.initModals();
};

//SG Modal popup logic
sgBackup.initModals = function(){
    jQuery('[data-toggle="modal"][href], [data-toggle="modal"][data-remote]').off('click').on('click', function(e) {
        var param = '';
        if (typeof jQuery(this).attr('data-sgbp-params') !== 'undefined'){
            param = jQuery(this).attr('data-sgbp-params');
        }

        e.preventDefault();
        var btn = jQuery(this),
            url = btn.attr('data-remote'),
            modalName = btn.attr('data-modal-name'),
            modal = jQuery('#sg-modal');
        if( modal.length == 0 ) {
            modal = jQuery('' +
            '<div class="modal fade" id="sg-modal" tabindex="-1" role="dialog" aria-hidden="true"></div>' +
            '');
            body.append(modal);
        }
        sgBackup.showAjaxSpinner('#sg-content-wrapper');

        var ajaxHandler = new sgRequestHandler(url, {param: param});
        ajaxHandler.type = 'GET';
        ajaxHandler.dataType = 'html';
        ajaxHandler.callback = function(data, error) {
            sgBackup.hideAjaxSpinner();
            if (error===false) {
                jQuery('#sg-modal').append(data);
            }
            modal.on('hide.bs.modal', function() {
                if(SG_CURRENT_ACTIVE_AJAX != '') {
                    if (!confirm('Are you sure you want to cancel?')) {
                        return false;
                    }
                    SG_CURRENT_ACTIVE_AJAX.abort();
                    SG_CURRENT_ACTIVE_AJAX = '';
                }
            });
            modal.one('hidden.bs.modal', function() {
                modal.html('');
            }).modal('show');
            sgBackup.didOpenModal(modalName);
        };
        ajaxHandler.run();
    });
};

sgBackup.didOpenModal = function(modalName){
    if(modalName == 'manual-backup'){
        sgBackup.initManulBackupRadioInputs();
        sgBackup.initManualBackupTooltips();
    }
    else if(modalName == 'import'){
        jQuery('#modal-import-2').hide();
        jQuery('#modal-import-3').hide();
        jQuery('#switch-modal-import-pages-back').hide();
        jQuery('#uploadSgbpFile').hide();
        if(jQuery('#modal-import-1').length == 0) {
            sgBackup.toggleDownloadFromPCPage();
        }
        sgBackup.initFileUpload();
    }
    else if(modalName == 'ftp-settings'){
        jQuery('#sg-modal').on('hidden.bs.modal', function () {
            if(sgBackup.isFtpConnected != true) {
                jQuery('input[data-storage=ftp]').bootstrapSwitch('state', false);
            }
        })
    }
    else if(modalName == 'manual-review'){
        var action = 'setReviewPopupState';
        jQuery('#sgLeaveReview').click(function(){
            var reviewUrl = jQuery(this).attr('data-review-url');
            //Never show again
            var reviewState = 2;
            var ajaxHandler = new sgRequestHandler(action, {reviewState: reviewState});
            ajaxHandler.run();
            window.open(reviewUrl);
        });

        jQuery('#sgDontAskAgain').click(function(){
            //Never show again
            var reviewState = 2;
            var ajaxHandler = new sgRequestHandler(action, {reviewState: reviewState});
            ajaxHandler.run();
        });

        jQuery('#sgAskLater').click(function(){
            var reviewState = 0;
            var ajaxHandler = new sgRequestHandler(action, {reviewState: reviewState});
            ajaxHandler.run();
        });
    }
    else if(modalName == 'create-schedule') {
        sgBackup.initScheduleCreation();
    }
};

sgBackup.isAnyOpenModal = function(){
    return jQuery('#sg-modal').length;
};

sgBackup.alertGenerator = function(content, alertClass){
    var sgalert = '';
    sgalert+='<div class="alert alert-dismissible '+alertClass+'">';
    sgalert+='<button type="button" class="close" data-dismiss="alert">×</button>';
    if(jQuery.isArray(content)){
        jQuery.each(content, function(index, value) {
            sgalert+=value+'<br/>';
        });
    }
    else if(content != ''){
        sgalert+=content.replace('[','').replace(']','').replace('"','');
    }
    sgalert+='</div>';
    return sgalert;
};

sgBackup.scrollToElement = function(id){
    if(jQuery(id).position()){
        if(jQuery(id).position().top < jQuery(window).scrollTop()){
            //scroll up
            jQuery('html,body').animate({scrollTop:jQuery(id).position().top}, 1000);
        }
        else if(jQuery(id).position().top + jQuery(id).height() > jQuery(window).scrollTop() + (window.innerHeight || document.documentElement.clientHeight)){
            //scroll down
            jQuery('html,body').animate({scrollTop:jQuery(id).position().top - (window.innerHeight || document.documentElement.clientHeight) + jQuery(id).height() + 15}, 1000);
        }
    }
};

sgBackup.showAjaxSpinner = function(appendToElement){
    if(typeof appendToElement == 'undefined'){
        appendToElement = '#sg-wrapper';
    }
    jQuery('<div class="sg-spinner"></div>').appendTo(appendToElement);
};

sgBackup.hideAjaxSpinner = function(){
    jQuery('.sg-spinner').remove();
};

sgBackup.showReviewModal = function(){
    if(typeof sgShowReview != 'undefined') {
        jQuery('#sg-review').trigger("click");
    }
};

sgBackup.initTablePagination = function(){
    jQuery.fn.sgTablePagination = function(opts){
        var jQuerythis = this,
            defaults = {
                perPage: 7,
                showPrevNext: false,
                hidePageNumbers: false,
                pagerSelector: 'pagination'
            },
            settings = jQuery.extend(defaults, opts);

        var listElement = jQuerythis.children('tbody');
        var perPage = settings.perPage;
        var children = listElement.children();
        var pager = jQuery('.pager');

        if (typeof settings.childSelector!="undefined") {
            children = listElement.find(settings.childSelector);
        }

        if (typeof settings.pagerSelector!="undefined") {
            pager = jQuery(settings.pagerSelector);
        }

        var numItems = children.size();
        var numPages = Math.ceil(numItems/perPage);

        pager.data("curr",0);

        if (settings.showPrevNext){
            jQuery('<li><a href="#" class="prev_link">«</a></li>').appendTo(pager);
        }

        var curr = 0;
        while(numPages > curr && (settings.hidePageNumbers==false)){
            jQuery('<li><a href="#" class="page_link">'+(curr+1)+'</a></li>').appendTo(pager);
            curr++;
        }

        if(curr<=1){
            jQuery(settings.pagerSelector).parent('div').hide();
            jQuery('.page_link').hide();
        }

        if (settings.showPrevNext){
            jQuery('<li><a href="#" class="next_link">»</a></li>').appendTo(pager);
        }

        pager.find('.page_link:first').addClass('active');
        pager.find('.prev_link').hide();
        if (numPages<=1) {
            pager.find('.next_link').hide();
        }
        pager.children().eq(1).addClass("active");

        children.hide();
        children.slice(0, perPage).show();

        pager.find('li .page_link').click(function(){
            var clickedPage = jQuery(this).html().valueOf()-1;
            goTo(clickedPage,perPage);
            return false;
        });
        pager.find('li .prev_link').click(function(){
            previous();
            return false;
        });
        pager.find('li .next_link').click(function(){
            next();
            return false;
        });

        function previous(){
            var goToPage = parseInt(pager.data("curr")) - 1;
            goTo(goToPage);
        }

        function next(){
            goToPage = parseInt(pager.data("curr")) + 1;
            goTo(goToPage);
        }

        function goTo(page){
            var startAt = page * perPage,
                endOn = startAt + perPage;

            children.css('display','none').slice(startAt, endOn).show();

            if (page>=1) {
                pager.find('.prev_link').show();
            }
            else {
                pager.find('.prev_link').hide();
            }

            if (page<(numPages-1)) {
                pager.find('.next_link').show();
            }
            else {
                pager.find('.next_link').hide();
            }

            pager.data("curr",page);
            pager.children().removeClass("active");
            pager.children().eq(page+1).addClass("active");

        }
    };
    jQuery('table.paginated').sgTablePagination({pagerSelector:'.pagination',showPrevNext:true,hidePageNumbers:false,perPage:7});
};
