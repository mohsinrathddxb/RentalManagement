package com.colivingspace.mobile_app

import android.content.Intent
import android.net.Uri
import android.os.Bundle
import android.webkit.WebStorage
import androidx.core.content.FileProvider
import io.flutter.embedding.engine.FlutterEngine
import io.flutter.plugin.common.MethodChannel
import io.flutter.embedding.android.FlutterActivity
import java.io.File

class MainActivity : FlutterActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        resetCorruptedWebViewStateIfNeeded()
        super.onCreate(savedInstanceState)
    }

    override fun configureFlutterEngine(flutterEngine: FlutterEngine) {
        super.configureFlutterEngine(flutterEngine)

        MethodChannel(
            flutterEngine.dartExecutor.binaryMessenger,
            CHANNEL_SHARE_PDF
        ).setMethodCallHandler { call, result ->
            if (call.method != "sharePdf") {
                result.notImplemented()
                return@setMethodCallHandler
            }

            val filePath = call.argument<String>("filePath")
            val target = call.argument<String>("target") ?: "system"
            val subject = call.argument<String>("subject") ?: "Co-Living Space PDF"
            val message = call.argument<String>("message") ?: "Please find the attached PDF."

            if (filePath.isNullOrBlank()) {
                result.error("missing_file", "PDF file path is required.", null)
                return@setMethodCallHandler
            }

            sharePdf(filePath, target, subject, message, result)
        }
    }

    private fun resetCorruptedWebViewStateIfNeeded() {
        val prefs = getSharedPreferences("app_bootstrap", MODE_PRIVATE)
        if (prefs.getBoolean(KEY_WEBVIEW_RESET_DONE, false)) {
            return
        }

        runCatching {
            WebStorage.getInstance().deleteAllData()
            android.webkit.CookieManager.getInstance().removeAllCookies(null)
            android.webkit.CookieManager.getInstance().flush()

            val webViewDir = File(filesDir.parentFile, "app_webview")
            webViewDir.deleteRecursively()
        }

        prefs.edit().putBoolean(KEY_WEBVIEW_RESET_DONE, true).apply()
    }

    private fun sharePdf(
        filePath: String,
        target: String,
        subject: String,
        message: String,
        result: MethodChannel.Result
    ) {
        val file = File(filePath)
        if (!file.exists()) {
            result.error("file_not_found", "The generated PDF file was not found.", null)
            return
        }

        val uri = FileProvider.getUriForFile(
            this,
            "${applicationContext.packageName}.fileprovider",
            file
        )

        val baseIntent = Intent(Intent.ACTION_SEND).apply {
            type = "application/pdf"
            putExtra(Intent.EXTRA_STREAM, uri)
            putExtra(Intent.EXTRA_SUBJECT, subject)
            putExtra(Intent.EXTRA_TEXT, message)
            addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION)
        }

        val launchIntent = when (target.lowercase()) {
            "whatsapp" -> intentForPackages(
                baseIntent,
                listOf("com.whatsapp", "com.whatsapp.w4b")
            )
            "telegram" -> intentForPackages(
                baseIntent,
                listOf("org.telegram.messenger", "org.thunderdog.challegram")
            )
            "email" -> buildEmailChooser(baseIntent)
            else -> Intent.createChooser(baseIntent, "Share PDF")
        }

        if (launchIntent == null) {
            result.error(
                "share_target_missing",
                when (target.lowercase()) {
                    "whatsapp" -> "WhatsApp is not installed on this device."
                    "telegram" -> "Telegram is not installed on this device."
                    "email" -> "No email app is installed on this device."
                    else -> "No compatible app was found to share this PDF."
                },
                null
            )
            return
        }

        runCatching {
            startActivity(launchIntent)
        }.onSuccess {
            result.success(true)
        }.onFailure { error ->
            result.error("share_failed", error.message ?: "Could not share the PDF.", null)
        }
    }

    private fun intentForPackages(baseIntent: Intent, packages: List<String>): Intent? {
        packages.forEach { packageName ->
            val intent = Intent(baseIntent).apply { `package` = packageName }
            if (intent.resolveActivity(packageManager) != null) {
                return intent
            }
        }
        return null
    }

    private fun buildEmailChooser(baseIntent: Intent): Intent? {
        val mailIntent = Intent(Intent.ACTION_SENDTO).apply {
            data = Uri.parse("mailto:")
        }
        val emailPackages = packageManager.queryIntentActivities(mailIntent, 0)
            .map { it.activityInfo.packageName }
            .distinct()

        if (emailPackages.isEmpty()) {
            return null
        }

        val targetedIntents = emailPackages.map { packageName ->
            Intent(baseIntent).apply { `package` = packageName }
        }

        val primaryIntent = targetedIntents.first()
        val chooser = Intent.createChooser(primaryIntent, "Share PDF by email")
        if (targetedIntents.size > 1) {
            chooser.putExtra(
                Intent.EXTRA_INITIAL_INTENTS,
                targetedIntents.drop(1).toTypedArray()
            )
        }
        return chooser
    }

    private companion object {
        const val CHANNEL_SHARE_PDF = "co_living_space/share_pdf"
        const val KEY_WEBVIEW_RESET_DONE = "webview_reset_done"
    }
}
