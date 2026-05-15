import 'dart:async';
import 'dart:convert';
import 'dart:io' as io;

import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_pdfview/flutter_pdfview.dart';
import 'package:flutter/services.dart';
import 'package:flutter_inappwebview/flutter_inappwebview.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:path_provider/path_provider.dart';
import 'package:url_launcher/url_launcher.dart';

const Color kMidnightNavy = Color(0xFF071A2D);
const Color kPremiumGold = Color(0xFFC8A449);
const Color kPearl = Color(0xFFF6F2E8);

const String kBaseUrl = 'https://co-livingspace.free.nf/Rental-house-management-system/';
const String kTenantPortalUrl = '${kBaseUrl}admin/login.php';
const String kWebsiteUrl = kBaseUrl;
const String kTelegramBotUrl = 'https://t.me/livingspacedxbbot';
const MethodChannel kSharePdfChannel = MethodChannel('co_living_space/share_pdf');

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  SystemChrome.setSystemUIOverlayStyle(
    const SystemUiOverlayStyle(
      statusBarColor: Colors.transparent,
      statusBarIconBrightness: Brightness.light,
      statusBarBrightness: Brightness.dark,
    ),
  );
  runApp(const CoLivingSpaceApp());
}

class CoLivingSpaceApp extends StatelessWidget {
  const CoLivingSpaceApp({super.key});

  @override
  Widget build(BuildContext context) {
    final colorScheme = ColorScheme.fromSeed(
      seedColor: kPremiumGold,
      brightness: Brightness.light,
      primary: kPremiumGold,
      secondary: kMidnightNavy,
      surface: kPearl,
    );

    return MaterialApp(
      title: 'Co-Living Space',
      debugShowCheckedModeBanner: false,
      theme: ThemeData(
        useMaterial3: true,
        colorScheme: colorScheme,
        scaffoldBackgroundColor: kPearl,
        appBarTheme: const AppBarTheme(
          backgroundColor: kMidnightNavy,
          foregroundColor: kPearl,
          elevation: 0,
          centerTitle: false,
        ),
        cardTheme: CardThemeData(
          color: Colors.white.withValues(alpha: 0.92),
          elevation: 0,
          shadowColor: kMidnightNavy.withValues(alpha: 0.10),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(24),
            side: BorderSide(
              color: kPremiumGold.withValues(alpha: 0.20),
            ),
          ),
        ),
        textTheme: ThemeData.light().textTheme.apply(
              bodyColor: kMidnightNavy,
              displayColor: kMidnightNavy,
            ),
      ),
      home: const SplashScreen(),
    );
  }
}

class SplashScreen extends StatefulWidget {
  const SplashScreen({super.key});

  @override
  State<SplashScreen> createState() => _SplashScreenState();
}

class _SplashScreenState extends State<SplashScreen> {
  @override
  void initState() {
    super.initState();
    Timer(const Duration(milliseconds: 1600), () {
      if (!mounted) {
        return;
      }
      Navigator.of(context).pushReplacement(
        MaterialPageRoute<void>(
          builder: (_) => const LandingScreen(),
        ),
      );
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Container(
        decoration: const BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topCenter,
            end: Alignment.bottomCenter,
            colors: [
              kMidnightNavy,
              Color(0xFF102742),
              kPearl,
            ],
            stops: [0, 0.42, 1],
          ),
        ),
        child: Center(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                width: 112,
                height: 112,
                padding: const EdgeInsets.all(18),
                decoration: BoxDecoration(
                  color: Colors.white.withValues(alpha: 0.10),
                  borderRadius: BorderRadius.circular(28),
                  border: Border.all(
                    color: kPremiumGold.withValues(alpha: 0.32),
                  ),
                  boxShadow: [
                    BoxShadow(
                      color: kPremiumGold.withValues(alpha: 0.20),
                      blurRadius: 28,
                      spreadRadius: 1,
                    ),
                  ],
                ),
                child: SvgPicture.asset('assets/logo.svg'),
              ),
              const SizedBox(height: 24),
              const Text(
                'Co-Living Space',
                style: TextStyle(
                  fontSize: 28,
                  fontWeight: FontWeight.w700,
                  color: kPearl,
                  letterSpacing: 0.8,
                ),
              ),
              const SizedBox(height: 10),
              Text(
                'Luxury rental management, now in your pocket.',
                style: TextStyle(
                  fontSize: 14,
                  color: kPearl.withValues(alpha: 0.88),
                ),
              ),
              const SizedBox(height: 34),
              const SizedBox(
                width: 28,
                height: 28,
                child: CircularProgressIndicator(
                  strokeWidth: 2.6,
                  valueColor: AlwaysStoppedAnimation<Color>(kPremiumGold),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class LandingScreen extends StatelessWidget {
  const LandingScreen({super.key});

  Future<void> _openExternal(String url) async {
    final uri = Uri.parse(url);
    await launchUrl(uri, mode: LaunchMode.externalApplication);
  }

  void _openPortal(BuildContext context, String title, String url) {
    Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (_) => PortalScreen(
          title: title,
          initialUrl: url,
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Container(
        decoration: const BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topCenter,
            end: Alignment.bottomCenter,
            colors: [
              Color(0xFFF8F5ED),
              kPearl,
              Color(0xFFF0EBDE),
            ],
          ),
        ),
        child: SafeArea(
          child: ListView(
            padding: const EdgeInsets.fromLTRB(22, 18, 22, 30),
            children: [
              Container(
                padding: const EdgeInsets.all(24),
                decoration: BoxDecoration(
                  gradient: const LinearGradient(
                    begin: Alignment.topLeft,
                    end: Alignment.bottomRight,
                    colors: [
                      kMidnightNavy,
                      Color(0xFF143150),
                    ],
                  ),
                  borderRadius: BorderRadius.circular(30),
                  boxShadow: [
                    BoxShadow(
                      color: kMidnightNavy.withValues(alpha: 0.20),
                      blurRadius: 28,
                      offset: const Offset(0, 14),
                    ),
                  ],
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Container(
                          width: 64,
                          height: 64,
                          padding: const EdgeInsets.all(10),
                          decoration: BoxDecoration(
                            color: Colors.white.withValues(alpha: 0.08),
                            borderRadius: BorderRadius.circular(18),
                            border: Border.all(
                              color: kPremiumGold.withValues(alpha: 0.42),
                            ),
                          ),
                          child: SvgPicture.asset('assets/logo.svg'),
                        ),
                        const SizedBox(width: 16),
                        const Expanded(
                          child: Text(
                            'Co-Living Space',
                            style: TextStyle(
                              color: kPearl,
                              fontWeight: FontWeight.w700,
                              fontSize: 24,
                            ),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 24),
                    Text(
                      'Android first, beautifully branded, and already connected to your live rental system.',
                      style: TextStyle(
                        color: kPearl.withValues(alpha: 0.92),
                        fontSize: 16,
                        height: 1.5,
                      ),
                    ),
                    const SizedBox(height: 20),
                    Wrap(
                      spacing: 10,
                      runSpacing: 10,
                      children: const [
                        _FeatureChip(label: 'Invoices & receipts'),
                        _FeatureChip(label: 'Complaints'),
                        _FeatureChip(label: 'Telegram-ready'),
                        _FeatureChip(label: 'Live portal access'),
                      ],
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 22),
              const Text(
                'Open your portal',
                style: TextStyle(
                  fontSize: 22,
                  fontWeight: FontWeight.w700,
                  color: kMidnightNavy,
                ),
              ),
              const SizedBox(height: 10),
              Text(
                'This first Android build uses your current web system inside a polished mobile shell, so we can keep moving while native APIs grow behind it.',
                style: TextStyle(
                  fontSize: 15,
                  color: kMidnightNavy.withValues(alpha: 0.72),
                  height: 1.5,
                ),
              ),
              const SizedBox(height: 18),
              _PortalCard(
                icon: Icons.person_rounded,
                title: 'Tenant Portal',
                description: 'Login, check invoices, see notices, raise complaints, and track updates.',
                buttonText: 'Open Tenant Login',
                onPressed: () => _openPortal(
                  context,
                  'Tenant Portal',
                  kTenantPortalUrl,
                ),
              ),
              const SizedBox(height: 14),
              _PortalCard(
                icon: Icons.admin_panel_settings_rounded,
                title: 'Main Website',
                description: 'Open the live website directly for broader access and public pages.',
                buttonText: 'Open Website',
                onPressed: () => _openPortal(
                  context,
                  'Co-Living Space',
                  kWebsiteUrl,
                ),
              ),
              const SizedBox(height: 14),
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(20),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text(
                        'Support and connect',
                        style: TextStyle(
                          fontSize: 18,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                      const SizedBox(height: 8),
                      Text(
                        'Need help receiving receipts or invoices? Connect through Telegram so your account can receive instant updates.',
                        style: TextStyle(
                          height: 1.5,
                          color: kMidnightNavy.withValues(alpha: 0.72),
                        ),
                      ),
                      const SizedBox(height: 16),
                      OutlinedButton.icon(
                        onPressed: () => _openExternal(kTelegramBotUrl),
                        icon: const Icon(Icons.send_rounded),
                        label: const Text('Open Telegram Bot'),
                        style: OutlinedButton.styleFrom(
                          foregroundColor: kPremiumGold,
                          side: BorderSide(
                            color: kPremiumGold.withValues(alpha: 0.70),
                          ),
                          padding: const EdgeInsets.symmetric(
                            horizontal: 18,
                            vertical: 14,
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class PortalScreen extends StatefulWidget {
  const PortalScreen({
    super.key,
    required this.title,
    required this.initialUrl,
  });

  final String title;
  final String initialUrl;

  @override
  State<PortalScreen> createState() => _PortalScreenState();
}

class _PortalScreenState extends State<PortalScreen> {
  final _cookieManager = CookieManager.instance();
  final Dio _dio = Dio();

  InAppWebViewController? _controller;
  bool _isLoading = true;
  bool _isOffline = false;
  bool _isDownloadingPdf = false;
  bool _handlingMobilePdfPage = false;
  double _progress = 0;

  String _absoluteUrl(String url) {
    final uri = Uri.parse(url);
    if (uri.hasScheme) {
      return url;
    }

    final base = Uri.parse(widget.initialUrl);
    return base.resolve(url).toString();
  }

  bool _shouldHandlePdf(String url) {
    final normalized = _absoluteUrl(url).toLowerCase();
    return normalized.contains('invoice-pdf.php') ||
        normalized.contains('payment-receipt-pdf.php') ||
        normalized.endsWith('.pdf');
  }

  bool _isMobilePdfApiPage(String url) {
    return _absoluteUrl(url).toLowerCase().contains('mobile-pdf.php');
  }

  String? _mobilePdfApiUrl(String url) {
    final absoluteUrl = _absoluteUrl(url);
    final uri = Uri.parse(absoluteUrl);
    final lowerPath = uri.path.toLowerCase();

    String? type;
    String? id;
    if (lowerPath.endsWith('/invoice-pdf.php') || lowerPath.endsWith('invoice-pdf.php')) {
      type = 'invoice';
      id = uri.queryParameters['invoice'];
    } else if (lowerPath.endsWith('/payment-receipt-pdf.php') ||
        lowerPath.endsWith('payment-receipt-pdf.php')) {
      type = 'receipt';
      id = uri.queryParameters['payment'];
    }

    if (type == null || id == null || id.isEmpty) {
      return null;
    }

    final params = Map<String, String>.from(uri.queryParameters)
      ..remove('invoice')
      ..remove('payment')
      ..['type'] = type
      ..['id'] = id;

    return uri.replace(path: uri.path.replaceFirst(RegExp(r'[^/]+$'), 'mobile-pdf.php'), queryParameters: params).toString();
  }

  Future<void> _watchConnectivity() async {
    final result = await Connectivity().checkConnectivity();
    if (!mounted) {
      return;
    }
    setState(() {
      _isOffline = result.contains(ConnectivityResult.none);
    });
  }

  Future<void> _refresh() async {
    await _controller?.reload();
  }

  Future<bool> _handleBackNavigation() async {
    final controller = _controller;
    if (controller != null) {
      final canGoBack = await controller.canGoBack();
      if (canGoBack) {
        await controller.goBack();
        return false;
      }
    }
    return true;
  }

  Future<void> _onBackPressed() async {
    final shouldPop = await _handleBackNavigation();
    if (!mounted || !shouldPop) {
      return;
    }
    Navigator.of(context).maybePop();
  }

  Future<void> _openExternal() async {
    final currentUrl = await _controller?.getUrl();
    final url = currentUrl?.toString() ?? widget.initialUrl;
    await launchUrl(
      Uri.parse(url),
      mode: LaunchMode.externalApplication,
    );
  }

  Future<void> _openPdfAsWebUrl(String url) async {
    final absoluteUrl = _absoluteUrl(url);
    _showMessage('Opening PDF in current session...');
    await _controller?.loadUrl(
      urlRequest: URLRequest(url: WebUri(absoluteUrl)),
    );
  }

  void _showMessage(String message) {
    if (!mounted) {
      return;
    }
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(message)),
    );
  }

  Future<List<io.Cookie>> _loadCookies(Uri uri) async {
    final cookies = await _cookieManager.getCookies(
      url: WebUri.uri(uri),
    );
    return cookies
        .map(
          (cookie) => io.Cookie(cookie.name, cookie.value)
            ..domain = cookie.domain ?? uri.host
            ..path = cookie.path ?? '/',
        )
        .toList();
  }

  String _buildCookieHeader(List<io.Cookie> cookies) {
    return cookies.map((cookie) => '${cookie.name}=${cookie.value}').join('; ');
  }

  String _filenameFromUrl(String url, String? contentDisposition) {
    if (contentDisposition != null && contentDisposition.contains('filename=')) {
      final match = RegExp(r'filename="?([^"]+)"?').firstMatch(contentDisposition);
      if (match != null && match.group(1) != null) {
        return match.group(1)!.trim();
      }
    }

    final uri = Uri.parse(url);
    final invoice = uri.queryParameters['invoice'];
    if (invoice != null && invoice.isNotEmpty) {
      return 'invoice_$invoice.pdf';
    }

    final payment = uri.queryParameters['payment'];
    if (payment != null && payment.isNotEmpty) {
      return 'receipt_$payment.pdf';
    }

    return 'document_${DateTime.now().millisecondsSinceEpoch}.pdf';
  }

  List<int>? _extractPdfBytes(List<int> bytes) {
    const pdfSignature = [37, 80, 68, 70];
    for (var index = 0; index <= bytes.length - pdfSignature.length; index++) {
      var matches = true;
      for (var offset = 0; offset < pdfSignature.length; offset++) {
        if (bytes[index + offset] != pdfSignature[offset]) {
          matches = false;
          break;
        }
      }
      if (matches) {
        return bytes.sublist(index);
      }
    }
    return null;
  }

  Future<void> _saveAndOpenPdfBytes({
    required String sourceUrl,
    required String base64Data,
    String? contentDisposition,
  }) async {
    if (_isDownloadingPdf) {
      _showMessage('PDF download already in progress...');
      return;
    }

    setState(() {
      _isDownloadingPdf = true;
    });

    try {
      final bytes = base64Decode(base64Data);
      final pdfBytes = _extractPdfBytes(bytes);
      if (pdfBytes == null) {
        _showMessage('Could not open the PDF. Server did not return a PDF file.');
        return;
      }

      final directory = await getApplicationDocumentsDirectory();
      final pdfDirectory = io.Directory('${directory.path}/pdf_downloads');
      if (!await pdfDirectory.exists()) {
        await pdfDirectory.create(recursive: true);
      }

      final filename = _filenameFromUrl(
        _absoluteUrl(sourceUrl),
        contentDisposition,
      );
      final file = io.File('${pdfDirectory.path}/$filename');
      await file.writeAsBytes(pdfBytes, flush: true);
      if (!mounted) {
        return;
      }

      _showMessage('Downloaded $filename');
      await Navigator.of(context).push(
        MaterialPageRoute<void>(
          builder: (_) => PdfViewerScreen(
            title: filename,
            filePath: file.path,
          ),
        ),
      );
    } catch (error) {
      _showMessage('Could not open the PDF: $error');
    } finally {
      if (mounted) {
        setState(() {
          _isDownloadingPdf = false;
        });
      }
    }
  }

  Future<void> _downloadAndOpenPdf(
    String url, {
    String? contentDisposition,
  }) async {
    if (_isDownloadingPdf) {
      _showMessage('PDF download already in progress...');
      return;
    }

    final absoluteUrl = _absoluteUrl(url);
    final mobilePdfApiUrl = _mobilePdfApiUrl(absoluteUrl);
    final uri = Uri.parse(absoluteUrl);
    final currentUrl = await _controller?.getUrl();
    final cookieUrl = currentUrl != null && currentUrl.toString().isNotEmpty
        ? Uri.parse(currentUrl.toString())
        : uri;

    setState(() {
      _isDownloadingPdf = true;
    });

    try {
      final cookies = <String, io.Cookie>{};
      for (final cookie in await _loadCookies(cookieUrl)) {
        cookies[cookie.name] = cookie;
      }
      for (final cookie in await _loadCookies(uri)) {
        cookies[cookie.name] = cookie;
      }
      final headers = <String, String>{};
      if (cookies.isNotEmpty) {
        headers['Cookie'] = _buildCookieHeader(cookies.values.toList());
      }
      headers['Accept'] = 'application/pdf,*/*';
      headers['Referer'] = currentUrl?.toString() ?? widget.initialUrl;

      final directory = await getApplicationDocumentsDirectory();
      final pdfDirectory = io.Directory('${directory.path}/pdf_downloads');
      if (!await pdfDirectory.exists()) {
        await pdfDirectory.create(recursive: true);
      }

      late final List<int> pdfBytes;
      late final String filename;

      if (mobilePdfApiUrl != null) {
        final response = await _dio.get<String>(
          mobilePdfApiUrl,
          options: Options(
            responseType: ResponseType.plain,
            headers: headers,
            followRedirects: true,
            receiveTimeout: const Duration(seconds: 60),
            sendTimeout: const Duration(seconds: 60),
          ),
        );
        final rawPayload = (response.data ?? '').trim();
        if (rawPayload.isEmpty) {
          throw Exception('Server returned an empty PDF response.');
        }

        final decodedPayload = jsonDecode(rawPayload);
        if (decodedPayload is! Map<String, dynamic>) {
          throw Exception('Server returned an invalid PDF response.');
        }

        final payload = decodedPayload;
        if (payload['success'] != true || payload['data'] is! String) {
          throw Exception(payload['message'] ?? 'Server did not return the generated PDF.');
        }

        final decodedBytes = base64Decode(payload['data'] as String);
        final extractedBytes = _extractPdfBytes(decodedBytes);
        if (extractedBytes == null) {
          throw Exception('Server generated an invalid PDF response.');
        }

        pdfBytes = extractedBytes;
        filename = (payload['filename'] as String?) ?? _filenameFromUrl(absoluteUrl, contentDisposition);
      } else {
        final response = await _dio.get<List<int>>(
          absoluteUrl,
          options: Options(
            responseType: ResponseType.bytes,
            headers: headers,
            followRedirects: true,
            receiveTimeout: const Duration(seconds: 60),
            sendTimeout: const Duration(seconds: 60),
          ),
        );
        final bytes = response.data ?? <int>[];
        if (bytes.isEmpty) {
          throw Exception('Empty PDF response');
        }

        final extractedBytes = _extractPdfBytes(bytes);
        if (extractedBytes == null) {
          throw Exception('Server did not return a PDF file.');
        }

        pdfBytes = extractedBytes;
        filename = _filenameFromUrl(
          absoluteUrl,
          contentDisposition ?? response.headers.value('content-disposition'),
        );
      }

      final file = io.File('${pdfDirectory.path}/$filename');
      await file.writeAsBytes(pdfBytes, flush: true);
      if (!mounted) {
        return;
      }
      _showMessage('Downloaded $filename');
      await Navigator.of(context).push(
        MaterialPageRoute<void>(
          builder: (_) => PdfViewerScreen(
            title: filename,
            filePath: file.path,
          ),
        ),
      );
    } catch (error) {
      _showMessage('Could not open the PDF: $error');
    } finally {
      if (mounted) {
        setState(() {
          _isDownloadingPdf = false;
        });
      }
    }
  }

  Future<void> _openPdfViaMobilePage(String url) async {
    final mobilePdfApiUrl = _mobilePdfApiUrl(url);
    if (mobilePdfApiUrl == null) {
      await _downloadAndOpenPdf(url);
      return;
    }

    _showMessage('Preparing PDF...');
    await _controller?.loadUrl(
      urlRequest: URLRequest(url: WebUri(mobilePdfApiUrl)),
    );
  }

  Future<void> _handleMobilePdfPage(InAppWebViewController controller) async {
    if (_handlingMobilePdfPage) {
      return;
    }

    _handlingMobilePdfPage = true;
    try {
      final payloadJson = await controller.evaluateJavascript(source: r'''
        (function () {
          if (window.__coLivingMobilePdfPayload) {
            return JSON.stringify(window.__coLivingMobilePdfPayload);
          }
          return '';
        })();
      ''');

      final rawPayload = payloadJson?.toString() ?? '';
      if (rawPayload.isEmpty) {
        _showMessage('Could not open the PDF. Server did not provide PDF data.');
        return;
      }

      final decodedPayload = jsonDecode(rawPayload);
      if (decodedPayload is! Map<String, dynamic>) {
        _showMessage('Could not open the PDF. Invalid server PDF data.');
        return;
      }

      if (decodedPayload['success'] != true || decodedPayload['data'] is! String) {
        _showMessage(decodedPayload['message']?.toString() ?? 'Could not open the PDF.');
        return;
      }

      await _saveAndOpenPdfBytes(
        sourceUrl: 'mobile-pdf.php',
        base64Data: decodedPayload['data'] as String,
        contentDisposition: decodedPayload['filename'] is String
            ? 'filename="${decodedPayload['filename']}"'
            : null,
      );
    } catch (error) {
      _showMessage('Could not open the PDF: $error');
    } finally {
      _handlingMobilePdfPage = false;
    }
  }

  Future<void> _installPdfLinkHandler(InAppWebViewController controller) async {
    await controller.evaluateJavascript(source: r'''
      window.__coLivingPdfHandlerInstalled = false;
    ''');
  }

  @override
  Widget build(BuildContext context) {
    return PopScope(
      canPop: false,
      onPopInvokedWithResult: (didPop, result) async {
        if (didPop) {
          return;
        }
        await _onBackPressed();
      },
      child: Scaffold(
        appBar: AppBar(
          leading: IconButton(
            tooltip: 'Back',
            onPressed: _onBackPressed,
            icon: const Icon(Icons.arrow_back_rounded),
          ),
          title: Text(widget.title),
          actions: [
            if (_isDownloadingPdf)
              const Padding(
                padding: EdgeInsets.symmetric(horizontal: 14),
                child: Center(
                  child: SizedBox(
                    width: 18,
                    height: 18,
                    child: CircularProgressIndicator(
                      strokeWidth: 2.2,
                      valueColor: AlwaysStoppedAnimation<Color>(kPremiumGold),
                    ),
                  ),
                ),
              ),
            IconButton(
              tooltip: 'Refresh',
              onPressed: _refresh,
              icon: const Icon(Icons.refresh_rounded),
            ),
            IconButton(
              tooltip: 'Open in browser',
              onPressed: _openExternal,
              icon: const Icon(Icons.open_in_browser_rounded),
            ),
          ],
          bottom: PreferredSize(
            preferredSize: const Size.fromHeight(3),
            child: AnimatedOpacity(
              opacity: _isLoading ? 1 : 0,
              duration: const Duration(milliseconds: 250),
              child: LinearProgressIndicator(
                value: _progress == 0 ? null : _progress,
                minHeight: 3,
                backgroundColor: Colors.white.withValues(alpha: 0.10),
                valueColor: const AlwaysStoppedAnimation<Color>(kPremiumGold),
              ),
            ),
          ),
        ),
        body: Stack(
          children: [
            InAppWebView(
              initialUrlRequest: URLRequest(
                url: WebUri(widget.initialUrl),
              ),
              initialSettings: InAppWebViewSettings(
                javaScriptEnabled: true,
                useShouldOverrideUrlLoading: true,
                useOnDownloadStart: true,
                mediaPlaybackRequiresUserGesture: false,
                allowsInlineMediaPlayback: true,
                transparentBackground: true,
              ),
              onWebViewCreated: (controller) {
                _controller = controller;
                controller.addJavaScriptHandler(
                  handlerName: 'downloadPdf',
                  callback: (arguments) async {
                    if (arguments.isNotEmpty && arguments.first is String) {
                      await _downloadAndOpenPdf(arguments.first as String);
                    }
                    return null;
                  },
                );
                controller.addJavaScriptHandler(
                  handlerName: 'openPdfAsWebUrl',
                  callback: (arguments) async {
                    if (arguments.isNotEmpty && arguments.first is String) {
                      await _openPdfAsWebUrl(arguments.first as String);
                    }
                    return null;
                  },
                );
                controller.addJavaScriptHandler(
                  handlerName: 'savePdfBytes',
                  callback: (arguments) async {
                    if (arguments.length >= 2 &&
                        arguments[0] is String &&
                        arguments[1] is String) {
                      await _saveAndOpenPdfBytes(
                        sourceUrl: arguments[0] as String,
                        base64Data: arguments[1] as String,
                        contentDisposition: arguments.length >= 3 &&
                                arguments[2] is String
                            ? arguments[2] as String
                            : null,
                      );
                    }
                    return null;
                  },
                );
              },
              shouldOverrideUrlLoading: (controller, navigationAction) async {
                final url = navigationAction.request.url?.toString();
                if (url != null && _shouldHandlePdf(url)) {
                  await _openPdfViaMobilePage(url);
                  return NavigationActionPolicy.CANCEL;
                }
                return NavigationActionPolicy.ALLOW;
              },
              onCreateWindow: (controller, createWindowAction) async {
                final url = createWindowAction.request.url?.toString();
                if (url != null && _shouldHandlePdf(url)) {
                  await _openPdfViaMobilePage(url);
                  return false;
                }
                if (url != null) {
                  await controller.loadUrl(
                    urlRequest: URLRequest(url: WebUri(url)),
                  );
                }
                return false;
              },
              onDownloadStartRequest: (controller, request) async {
                final url = request.url.toString();
                if (_shouldHandlePdf(url)) {
                  await _openPdfViaMobilePage(url);
                } else {
                  await launchUrl(
                    Uri.parse(url),
                    mode: LaunchMode.externalApplication,
                  );
                }
              },
              onLoadStart: (_, url) {
                if (mounted) {
                  setState(() {
                    _isLoading = true;
                  });
                }
              },
              onLoadStop: (_, url) async {
                final controller = _controller;
                if (controller != null) {
                  await _installPdfLinkHandler(controller);
                  if (url != null && _isMobilePdfApiPage(url.toString())) {
                    await _handleMobilePdfPage(controller);
                  }
                }
                await _watchConnectivity();
                if (mounted) {
                  setState(() {
                    _isLoading = false;
                  });
                }
              },
              onProgressChanged: (_, progress) {
                if (mounted) {
                  setState(() {
                    _progress = progress / 100;
                  });
                }
              },
              onReceivedError: (_, request, error) {
                if (mounted) {
                  setState(() {
                    _isLoading = false;
                  });
                }
              },
            ),
            if (_isOffline)
              Positioned.fill(
                child: Container(
                  color: kPearl.withValues(alpha: 0.96),
                  padding: const EdgeInsets.all(24),
                  child: Center(
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        const Icon(
                          Icons.wifi_off_rounded,
                          size: 54,
                          color: kMidnightNavy,
                        ),
                        const SizedBox(height: 16),
                        const Text(
                          'No internet connection',
                          style: TextStyle(
                            fontSize: 20,
                            fontWeight: FontWeight.w700,
                            color: kMidnightNavy,
                          ),
                        ),
                        const SizedBox(height: 8),
                        Text(
                          'Reconnect and tap the refresh icon to load the portal again.',
                          textAlign: TextAlign.center,
                          style: TextStyle(
                            color: kMidnightNavy.withValues(alpha: 0.72),
                            height: 1.5,
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }
}

class PdfViewerScreen extends StatefulWidget {
  const PdfViewerScreen({
    super.key,
    required this.title,
    required this.filePath,
  });

  final String title;
  final String filePath;

  @override
  State<PdfViewerScreen> createState() => _PdfViewerScreenState();
}

class _PdfViewerScreenState extends State<PdfViewerScreen> {
  bool _isReady = false;
  bool _isPromptingShare = false;
  String? _errorMessage;
  int _totalPages = 0;
  int _currentPage = 0;

  void _showMessage(String message) {
    if (!mounted) {
      return;
    }
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(message)),
    );
  }

  Future<void> _sharePdf(String target) async {
    try {
      await kSharePdfChannel.invokeMethod<void>('sharePdf', {
        'filePath': widget.filePath,
        'target': target,
        'subject': widget.title,
        'message': 'Please find the attached PDF from Co-Living Space.',
      });
    } on PlatformException catch (error) {
      _showMessage(error.message ?? 'Could not share this PDF.');
    } catch (_) {
      _showMessage('Could not share this PDF.');
    }
  }

  Future<void> _openSharePrompt() async {
    if (!mounted || _isPromptingShare) {
      return;
    }

    setState(() {
      _isPromptingShare = true;
    });

    try {
      final target = await showModalBottomSheet<String>(
        context: context,
        backgroundColor: Colors.transparent,
        builder: (context) {
          return SafeArea(
            child: Container(
              margin: const EdgeInsets.fromLTRB(16, 0, 16, 16),
              padding: const EdgeInsets.all(20),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(24),
                border: Border.all(
                  color: kPremiumGold.withValues(alpha: 0.28),
                ),
                boxShadow: [
                  BoxShadow(
                    color: kMidnightNavy.withValues(alpha: 0.16),
                    blurRadius: 24,
                    offset: const Offset(0, 12),
                  ),
                ],
              ),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text(
                    'Share this PDF',
                    style: TextStyle(
                      fontSize: 20,
                      fontWeight: FontWeight.w700,
                      color: kMidnightNavy,
                    ),
                  ),
                  const SizedBox(height: 8),
                  Text(
                    'Choose where to send the generated invoice or receipt.',
                    style: TextStyle(
                      color: kMidnightNavy.withValues(alpha: 0.72),
                      height: 1.5,
                    ),
                  ),
                  const SizedBox(height: 18),
                  Row(
                    children: [
                      Expanded(
                        child: _ShareOptionTile(
                          icon: Icons.chat_rounded,
                          label: 'WhatsApp',
                          onTap: () => Navigator.of(context).pop('whatsapp'),
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: _ShareOptionTile(
                          icon: Icons.send_rounded,
                          label: 'Telegram',
                          onTap: () => Navigator.of(context).pop('telegram'),
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: _ShareOptionTile(
                          icon: Icons.email_rounded,
                          label: 'Email',
                          onTap: () => Navigator.of(context).pop('email'),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 16),
                  Align(
                    alignment: Alignment.centerRight,
                    child: TextButton(
                      onPressed: () => Navigator.of(context).pop(),
                      child: const Text('Not now'),
                    ),
                  ),
                ],
              ),
            ),
          );
        },
      );

      if (target != null && target.isNotEmpty) {
        await _sharePdf(target);
      }
    } finally {
      if (mounted) {
        setState(() {
          _isPromptingShare = false;
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(
          widget.title,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
        ),
        actions: [
          TextButton.icon(
            onPressed: (_isReady && _errorMessage == null) ? _openSharePrompt : null,
            icon: const Icon(Icons.share_rounded),
            label: const Text('Share PDF'),
            style: TextButton.styleFrom(
              foregroundColor: kPearl,
              disabledForegroundColor: kPearl.withValues(alpha: 0.45),
            ),
          ),
        ],
      ),
      body: Stack(
        children: [
          PDFView(
            filePath: widget.filePath,
            enableSwipe: true,
            swipeHorizontal: false,
            autoSpacing: true,
            pageFling: true,
            onRender: (pages) {
              if (!mounted) {
                return;
              }
              setState(() {
                _totalPages = pages ?? 0;
                _isReady = true;
              });
            },
            onError: (error) {
              if (!mounted) {
                return;
              }
              setState(() {
                _errorMessage = error.toString();
              });
            },
            onPageError: (page, error) {
              if (!mounted) {
                return;
              }
              setState(() {
                _errorMessage = 'Page $page: $error';
              });
            },
            onViewCreated: (_) {},
            onPageChanged: (page, total) {
              if (!mounted) {
                return;
              }
              setState(() {
                _currentPage = (page ?? 0) + 1;
                _totalPages = total ?? _totalPages;
              });
            },
          ),
          if (!_isReady && _errorMessage == null)
            const Center(
              child: CircularProgressIndicator(
                valueColor: AlwaysStoppedAnimation<Color>(kPremiumGold),
              ),
            ),
          if (_errorMessage != null)
            Center(
              child: Padding(
                padding: const EdgeInsets.all(24),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    const Icon(
                      Icons.picture_as_pdf_rounded,
                      size: 54,
                      color: kMidnightNavy,
                    ),
                    const SizedBox(height: 16),
                    const Text(
                      'Could not display PDF',
                      style: TextStyle(
                        fontSize: 20,
                        fontWeight: FontWeight.w700,
                        color: kMidnightNavy,
                      ),
                    ),
                    const SizedBox(height: 8),
                    Text(
                      _errorMessage!,
                      textAlign: TextAlign.center,
                      style: TextStyle(
                        color: kMidnightNavy.withValues(alpha: 0.72),
                        height: 1.5,
                      ),
                    ),
                  ],
                ),
              ),
            ),
          if (_isReady && _errorMessage == null && _totalPages > 0)
            Positioned(
              right: 16,
              bottom: 16,
              child: Container(
                padding: const EdgeInsets.symmetric(
                  horizontal: 12,
                  vertical: 8,
                ),
                decoration: BoxDecoration(
                  color: kMidnightNavy.withValues(alpha: 0.88),
                  borderRadius: BorderRadius.circular(999),
                  border: Border.all(
                    color: kPremiumGold.withValues(alpha: 0.45),
                  ),
                ),
                child: Text(
                  '$_currentPage / $_totalPages',
                  style: const TextStyle(
                    color: kPearl,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ),
            ),
        ],
      ),
    );
  }
}

class _ShareOptionTile extends StatelessWidget {
  const _ShareOptionTile({
    required this.icon,
    required this.label,
    required this.onTap,
  });

  final IconData icon;
  final String label;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: kPearl,
      borderRadius: BorderRadius.circular(18),
      child: InkWell(
        borderRadius: BorderRadius.circular(18),
        onTap: onTap,
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 16),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(18),
            border: Border.all(
              color: kPremiumGold.withValues(alpha: 0.34),
            ),
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(
                icon,
                color: kMidnightNavy,
                size: 24,
              ),
              const SizedBox(height: 10),
              Text(
                label,
                textAlign: TextAlign.center,
                style: const TextStyle(
                  color: kMidnightNavy,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _PortalCard extends StatelessWidget {
  const _PortalCard({
    required this.icon,
    required this.title,
    required this.description,
    required this.buttonText,
    required this.onPressed,
  });

  final IconData icon;
  final String title;
  final String description;
  final String buttonText;
  final VoidCallback onPressed;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Container(
              width: 46,
              height: 46,
              decoration: BoxDecoration(
                color: kPremiumGold.withValues(alpha: 0.16),
                borderRadius: BorderRadius.circular(14),
              ),
              child: Icon(
                icon,
                color: kMidnightNavy,
              ),
            ),
            const SizedBox(height: 16),
            Text(
              title,
              style: const TextStyle(
                fontSize: 20,
                fontWeight: FontWeight.w700,
              ),
            ),
            const SizedBox(height: 8),
            Text(
              description,
              style: TextStyle(
                height: 1.55,
                color: kMidnightNavy.withValues(alpha: 0.72),
              ),
            ),
            const SizedBox(height: 18),
            FilledButton.icon(
              onPressed: onPressed,
              style: FilledButton.styleFrom(
                backgroundColor: kPremiumGold,
                foregroundColor: kMidnightNavy,
                minimumSize: const Size.fromHeight(48),
              ),
              icon: Icon(icon),
              label: Text(buttonText),
            ),
          ],
        ),
      ),
    );
  }
}

class _FeatureChip extends StatelessWidget {
  const _FeatureChip({required this.label});

  final String label;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(999),
        border: Border.all(
          color: kPremiumGold.withValues(alpha: 0.34),
        ),
      ),
      child: Text(
        label,
        style: TextStyle(
          color: kPearl.withValues(alpha: 0.92),
          fontSize: 12,
          fontWeight: FontWeight.w600,
        ),
      ),
    );
  }
}
