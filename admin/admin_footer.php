<!-- /.container-fluid -->
            <footer class="footer text-center"> <?php echo 2000+date('y');?> &copy; Co- Accomodation </footer>
        </div>
        <!-- /#page-wrapper -->
    </div>
    <!-- /#wrapper -->
    <!-- Jquery Cookie Handler -->
    <script src="js/vendor/js.cookie.min.js"></script>
    <!-- jQuery -->
    <script src="../plugins/bower_components/jquery/dist/jquery.min.js"></script>
    <!-- Bootstrap Core JavaScript -->
    <script src="bootstrap/dist/js/tether.min.js"></script>
    <script src="bootstrap/dist/js/bootstrap.min.js"></script>
    <script src="../plugins/bower_components/bootstrap-extension/js/bootstrap-extension.min.js"></script>
    <!-- Menu Plugin JavaScript -->
    <script src="../plugins/bower_components/sidebar-nav/dist/sidebar-nav.min.js"></script>
    <!--slimscroll JavaScript -->
    <script src="js/jquery.slimscroll.js"></script>
    <!--Wave Effects -->
    <script src="js/waves.js"></script>
    <!--Counter js -->
    <script src="../plugins/bower_components/waypoints/lib/jquery.waypoints.js"></script>
    <script src="../plugins/bower_components/counterup/jquery.counterup.min.js"></script>
    <!--Morris JavaScript -->
    <script src="../plugins/bower_components/raphael/raphael-min.js"></script>
    <script src="../plugins/bower_components/morrisjs/morris.js"></script>
    <!-- Custom Theme JavaScript -->
    <script src="js/custom.min.js"></script>
    <?php if (basename($_SERVER['PHP_SELF']) === 'index.php' && function_exists('is_admin_user') && is_admin_user()) { ?>
    <script src="js/dashboard1.js"></script>
    <?php } ?>
    <!-- for tables-->
     <script src="../plugins/bower_components/datatables/jquery.dataTables.min.js"></script>
    <!-- start - This is for export functionality only -->
    <script src="js/vendor/dataTables.buttons.min.js"></script>
    <script src="js/vendor/buttons.flash.min.js"></script>
    <script src="js/vendor/jszip.min.js"></script>
    <script src="js/vendor/pdfmake.min.js"></script>
    <script src="js/vendor/vfs_fonts.js"></script>
    <script src="js/vendor/buttons.html5.min.js"></script>
    <script src="js/vendor/buttons.print.min.js"></script>
    <!-- end - This is for export functionality only -->
    <!-- Sparkline chart JavaScript -->
    <script src="../plugins/bower_components/jquery-sparkline/jquery.sparkline.min.js"></script>
    <script src="../plugins/bower_components/jquery-sparkline/jquery.charts-sparkline.js"></script>
    <script src="../plugins/bower_components/toast-master/js/jquery.toast.js"></script>

    <script type="text/javascript">
function store(name, val) {
    if (typeof (Storage) !== "undefined") {
      localStorage.setItem(name, val);
    } else {
      window.alert('Please use a modern browser to properly view this template!');
    }
}

function get(name) {
    if (typeof (Storage) !== "undefined") {
        return localStorage.getItem(name);
    }
    return null;
}

$(document).ready(function(){
    function syncMobileSidebarState() {
        if (window.matchMedia('(max-width: 767px)').matches) {
            if (!$('body').hasClass('mobile-sidebar-open')) {
                $('body').addClass('mobile-sidebar-closed');
            }
        } else {
            $('body').removeClass('mobile-sidebar-closed mobile-sidebar-open');
            $('.js-mobile-menu-toggle i').removeClass('ti-close').addClass('ti-menu');
        }
    }

    syncMobileSidebarState();
    $(window).on('resize', syncMobileSidebarState);

    $(document).on('click', '.js-mobile-menu-toggle', function(e) {
        if (!window.matchMedia('(max-width: 767px)').matches) {
            return;
        }
        e.preventDefault();
        $('body').toggleClass('mobile-sidebar-open').toggleClass('mobile-sidebar-closed');
        $(this).find('i').toggleClass('ti-menu ti-close');
    });

    $(document).on('click', '.mobile-sidebar-backdrop', function() {
        $('body').removeClass('mobile-sidebar-open').addClass('mobile-sidebar-closed');
        $('.js-mobile-menu-toggle i').removeClass('ti-close').addClass('ti-menu');
    });

 $("*[theme]").click(function(e){
      e.preventDefault();
        var currentStyle = $(this).attr('theme');

        Cookies.set('themeselection', currentStyle, { expires: 30 });
        store('theme', currentStyle);
        var themeselected= Cookies.get('themeselection');
        $('#theme').attr({href: 'css/colors/'+themeselected+'.css'})
    });

    var currentTheme = get('theme');
    if(currentTheme)
    {
      $('#theme').attr({href: 'css/colors/'+currentTheme+'.css'});
    }
    // color selector
    $('#themecolors').on('click', 'a', function(){
        $('#themecolors li a').removeClass('working');
        $(this).addClass('working')
      });

});

$(document).ready(function(){
    $("*[theme]").click(function(e){
      e.preventDefault();
        var currentStyle = Cookies.get('themeselection');
        store('theme', currentStyle);
        $('#theme').attr({href: 'css/colors/'+currentStyle+'.css'})
    });

    var currentTheme = Cookies.get('themeselection');
    if(currentTheme)
    {
      $('#theme').attr({href: 'css/colors/'+currentTheme+'.css'});
    }
    // color selector
$('#themecolors').on('click', 'a', function(){
        $('#themecolors li a').removeClass('working');
        $(this).addClass('working')
      });
});
    </script>
