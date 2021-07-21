$(document).ready(function () {
    if($('.sidebar.perfect-scrollbar').length){
        const ps1 = new PerfectScrollbar('.sidebar.perfect-scrollbar', {
            wheelSpeed: 2,
            wheelPropagation: true,
            minScrollbarLength: 20
        });
    }
    
    if($('.mainContainer.perfect-scrollbar').length){
        const ps2 = new PerfectScrollbar('.mainContainer.perfect-scrollbar', {
            wheelSpeed: 2,
            wheelPropagation: true,
            minScrollbarLength: 20
        });
    }

    $('#nav').find('li').find('a').click(function(){
        $('#nav').find('li').find('a').removeClass('active');
        $(this).addClass('active');
    });
});