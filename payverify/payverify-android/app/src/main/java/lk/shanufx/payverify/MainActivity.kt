package lk.shanufx.payverify

import android.Manifest
import android.content.Intent
import android.content.pm.PackageManager
import android.net.Uri
import android.os.Build
import android.os.Bundle
import android.os.PowerManager
import android.provider.Settings
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.compose.setContent
import androidx.activity.result.contract.ActivityResultContracts
import androidx.biometric.BiometricManager
import androidx.biometric.BiometricPrompt
import androidx.compose.animation.AnimatedVisibility
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.LazyRow
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.core.app.NotificationManagerCompat
import androidx.core.content.ContextCompat
import androidx.fragment.app.FragmentActivity
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import java.text.DateFormat
import java.util.Date

class MainActivity : FragmentActivity() {
    private var isUnlocked by mutableStateOf(false)

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        val store = App.store(this)

        if (store.biometricLockEnabled) {
            promptBiometric()
        } else {
            isUnlocked = true
        }

        setContent {
            MaterialTheme(
                colorScheme = darkColorScheme(
                    primary = Color(0xFF3DE0FF),
                    onPrimary = Color(0xFF00363F),
                    primaryContainer = Color(0xFF004E5B),
                    surface = Color(0xFF111418),
                    surfaceVariant = Color(0xFF1E232A),
                    background = Color(0xFF0B0D10)
                )
            ) {
                Surface(modifier = Modifier.fillMaxSize(), color = MaterialTheme.colorScheme.background) {
                    if (!isUnlocked && store.biometricLockEnabled) {
                        LockedScreen { promptBiometric() }
                    } else {
                        Root(store)
                    }
                }
            }
        }
    }

    private fun promptBiometric() {
        val executor = ContextCompat.getMainExecutor(this)
        val prompt = BiometricPrompt(this, executor, object : BiometricPrompt.AuthenticationCallback() {
            override fun onAuthenticationSucceeded(result: BiometricPrompt.AuthenticationResult) {
                isUnlocked = true
            }

            override fun onAuthenticationError(errorCode: Int, errString: CharSequence) {
                // If user cancels or device has no biometrics, allow PIN or retry
                if (errorCode == BiometricPrompt.ERROR_NO_BIOMETRICS || errorCode == BiometricPrompt.ERROR_HW_UNAVAILABLE) {
                    isUnlocked = true
                }
            }
        })

        val promptInfo = BiometricPrompt.PromptInfo.Builder()
            .setTitle("PayVerify Security")
            .setSubtitle("Authenticate to view financial bank messages")
            .setAllowedAuthenticators(BiometricManager.Authenticators.BIOMETRIC_STRONG or BiometricManager.Authenticators.DEVICE_CREDENTIAL)
            .build()

        prompt.authenticate(promptInfo)
    }
}

@Composable
private fun LockedScreen(onUnlockClick: () -> Unit) {
    Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
        Column(horizontalAlignment = Alignment.CenterHorizontally, verticalArrangement = Arrangement.spacedBy(16.dp)) {
            Icon(Icons.Filled.Lock, contentDescription = null, tint = MaterialTheme.colorScheme.primary, modifier = Modifier.size(56.dp))
            Text("PayVerify App Locked", style = MaterialTheme.typography.titleLarge)
            Text("Biometric or device PIN required to view messages", style = MaterialTheme.typography.bodyMedium, color = MaterialTheme.colorScheme.onSurfaceVariant)
            Button(onClick = onUnlockClick) {
                Icon(Icons.Filled.Fingerprint, contentDescription = null)
                Spacer(Modifier.width(8.dp))
                Text("Unlock App")
            }
        }
    }
}

private data class Snap(
    val enrolled: Boolean,
    val sms: Boolean,
    val listener: Boolean,
    val batteryOk: Boolean,
    val queue: Int,
    val lastSync: Long,
    val err: String,
    val unread: Int,
    val logs: List<LogRow>
)

@Composable
private fun Root(store: Store) {
    val ctx = androidx.compose.ui.platform.LocalContext.current
    var snap by remember { mutableStateOf<Snap?>(null) }

    fun read() = Snap(
        store.enrolled,
        ctx.checkSelfPermission(Manifest.permission.RECEIVE_SMS) == PackageManager.PERMISSION_GRANTED,
        NotificationManagerCompat.getEnabledListenerPackages(ctx).contains(ctx.packageName),
        (ctx.getSystemService(android.content.Context.POWER_SERVICE) as PowerManager).isIgnoringBatteryOptimizations(ctx.packageName),
        store.queueDepth(),
        store.lastSyncMs,
        store.lastError,
        store.unreadCount(),
        store.logs(50)
    )

    LaunchedEffect(Unit) {
        while (true) {
            snap = withContext(Dispatchers.IO) { read() }
            delay(3000)
        }
    }

    val s = snap ?: return
    if (!s.enrolled) {
        EnrolScreen(store) { snap = read() }
    } else {
        MainScreen(store, s)
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun MainScreen(store: Store, s: Snap) {
    // Mode 0: Messages Viewer (Relay Phone view), Mode 1: Admin Console
    var appMode by remember { mutableIntStateOf(0) }

    Scaffold(
        topBar = {
            TopAppBar(
                title = {
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Icon(Icons.Filled.Shield, contentDescription = null, tint = MaterialTheme.colorScheme.primary)
                        Spacer(Modifier.width(8.dp))
                        Text(if (appMode == 0) "Bank Messages" else "Admin Console", style = MaterialTheme.typography.titleMedium)
                    }
                },
                actions = {
                    // Mode Switcher Pill
                    FilterChip(
                        selected = appMode == 0,
                        onClick = { appMode = 0 },
                        label = { Text("Viewer") },
                        leadingIcon = { Icon(Icons.Filled.Sms, contentDescription = null, modifier = Modifier.size(16.dp)) }
                    )
                    Spacer(Modifier.width(4.dp))
                    FilterChip(
                        selected = appMode == 1,
                        onClick = { appMode = 1 },
                        label = { Text("Admin") },
                        leadingIcon = { Icon(Icons.Filled.AdminPanelSettings, contentDescription = null, modifier = Modifier.size(16.dp)) }
                    )
                    Spacer(Modifier.width(8.dp))
                },
                colors = TopAppBarDefaults.topAppBarColors(containerColor = MaterialTheme.colorScheme.surface)
            )
        }
    ) { pad ->
        Box(Modifier.fillMaxSize().padding(pad)) {
            if (appMode == 0) {
                DeviceMessagesViewer(store)
            } else {
                AdminConsole(store, s)
            }
        }
    }
}

// ==============================================================================================
// 1. DEVICE MESSAGES VIEWER (Smart Bank Cards, Search, Filters, Categories, Unread, Detail Sheet)
// ==============================================================================================
@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun DeviceMessagesViewer(store: Store) {
    val ctx = androidx.compose.ui.platform.LocalContext.current
    val scope = rememberCoroutineScope()
    var search by remember { mutableStateOf("") }
    var selectedCategory by remember { mutableStateOf("All") }
    var directionFilter by remember { mutableStateOf("all") }
    var unreadOnly by remember { mutableStateOf(false) }
    var messages by remember { mutableStateOf<List<MessageCardItem>>(emptyList()) }
    var selectedMessage by remember { mutableStateOf<MessageCardItem?>(null) }
    var isRefreshing by remember { mutableStateOf(false) }

    fun refreshMessages() {
        scope.launch {
            isRefreshing = true
            messages = withContext(Dispatchers.IO) {
                store.getMessages(
                    query = search,
                    directionFilter = directionFilter,
                    categoryFilter = selectedCategory,
                    unreadOnly = unreadOnly,
                    limit = 200
                )
            }
            isRefreshing = false
        }
    }

    LaunchedEffect(search, selectedCategory, directionFilter, unreadOnly) {
        refreshMessages()
    }

    Column(Modifier.fillMaxSize()) {
        // Search & Refresh Row
        Surface(color = MaterialTheme.colorScheme.surfaceVariant, modifier = Modifier.fillMaxWidth()) {
            Column(Modifier.padding(horizontal = 12.dp, vertical = 8.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                OutlinedTextField(
                    value = search,
                    onValueChange = { search = it },
                    placeholder = { Text("Search amount, ref, sender, branch...") },
                    leadingIcon = { Icon(Icons.Filled.Search, contentDescription = null) },
                    trailingIcon = {
                        if (search.isNotEmpty()) {
                            IconButton(onClick = { search = "" }) {
                                Icon(Icons.Filled.Clear, contentDescription = "Clear")
                            }
                        }
                    },
                    singleLine = true,
                    modifier = Modifier.fillMaxWidth(),
                    shape = RoundedCornerShape(12.dp)
                )

                // Category Filter Row
                val categories = listOf("All", "CRM Deposit", "Fund Transfer", "ATM Cash", "Credit Alert", "Debit Alert")
                LazyRow(horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                    items(categories) { cat ->
                        FilterChip(
                            selected = selectedCategory == cat,
                            onClick = { selectedCategory = cat },
                            label = { Text(cat, fontSize = 12.sp) }
                        )
                    }
                }

                // Quick Direction & Unread row
                Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
                    Row(horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                        FilterChip(
                            selected = directionFilter == "credit",
                            onClick = { directionFilter = if (directionFilter == "credit") "all" else "credit" },
                            label = { Text("Credits 🟢", fontSize = 12.sp) }
                        )
                        FilterChip(
                            selected = directionFilter == "debit",
                            onClick = { directionFilter = if (directionFilter == "debit") "all" else "debit" },
                            label = { Text("Debits 🔴", fontSize = 12.sp) }
                        )
                    }

                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Text("Unread", style = MaterialTheme.typography.labelMedium)
                        Spacer(Modifier.width(4.dp))
                        Switch(
                            checked = unreadOnly,
                            onCheckedChange = { unreadOnly = it }
                        )
                    }
                }
            }
        }

        // Messages List or Empty State
        if (messages.isEmpty()) {
            Box(Modifier.fillMaxSize().padding(32.dp), contentAlignment = Alignment.Center) {
                Column(horizontalAlignment = Alignment.CenterHorizontally, verticalArrangement = Arrangement.spacedBy(8.dp)) {
                    Icon(Icons.Filled.Inbox, contentDescription = null, tint = MaterialTheme.colorScheme.onSurfaceVariant, modifier = Modifier.size(48.dp))
                    Text("No Matching Bank Messages", style = MaterialTheme.typography.titleMedium)
                    Text("Incoming Commercial Bank SMS and notifications will appear here as formatted cards.", color = MaterialTheme.colorScheme.onSurfaceVariant, style = MaterialTheme.typography.bodySmall)
                    Spacer(Modifier.height(8.dp))
                    Button(onClick = {
                        Sync.request(ctx)
                        refreshMessages()
                    }) {
                        Icon(Icons.Filled.Sync, contentDescription = null)
                        Spacer(Modifier.width(6.dp))
                        Text("Sync & Refresh Outbox")
                    }
                }
            }
        } else {
            LazyColumn(
                modifier = Modifier.fillMaxSize().padding(horizontal = 12.dp, vertical = 8.dp),
                verticalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                items(messages, key = { it.id }) { msg ->
                    BankMessageCard(msg = msg) {
                        store.markAsRead(msg.id)
                        selectedMessage = msg
                        refreshMessages()
                    }
                }
            }
        }
    }

    // Detail Bottom Sheet
    selectedMessage?.let { msg ->
        MessageDetailSheet(
            msg = msg,
            onDismiss = { selectedMessage = null },
            onDelete = {
                selectedMessage = null
                refreshMessages()
            }
        )
    }
}

@Composable
private fun BankMessageCard(msg: MessageCardItem, onClick: () -> Unit) {
    val isCredit = msg.direction == "credit"
    val isDebit = msg.direction == "debit"
    val amountColor = if (isCredit) Color(0xFF34D399) else if (isDebit) Color(0xFFF87171) else MaterialTheme.colorScheme.onSurface

    Card(
        modifier = Modifier.fillMaxWidth().clickable { onClick() },
        shape = RoundedCornerShape(12.dp),
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant)
    ) {
        Column(Modifier.padding(14.dp), verticalArrangement = Arrangement.spacedBy(6.dp)) {
            // Header Row: Sender, Tag & Unread Dot
            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    if (!msg.isRead) {
                        Box(Modifier.size(8.dp).clip(CircleShape).background(MaterialTheme.colorScheme.primary))
                        Spacer(Modifier.width(6.dp))
                    }
                    Text(
                        text = BankParser.normalizeSender(msg.sender),
                        style = MaterialTheme.typography.titleSmall,
                        fontWeight = if (!msg.isRead) FontWeight.Bold else FontWeight.Normal
                    )
                }

                Surface(
                    shape = RoundedCornerShape(6.dp),
                    color = if (msg.state == "pending") Color(0xFF3B2F1B) else Color(0xFF1B3D2F)
                ) {
                    Text(
                        text = if (msg.state == "pending") "QUEUED" else "SYNCED",
                        style = MaterialTheme.typography.labelSmall,
                        color = if (msg.state == "pending") Color(0xFFFBBF24) else Color(0xFF34D399),
                        modifier = Modifier.padding(horizontal = 6.dp, vertical = 2.dp)
                    )
                }
            }

            // Amount & Category Row
            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
                Text(
                    text = if (msg.amountMinor != null) "Rs. ${msg.amountMinor / 100.0}" else "Non-monetary",
                    style = MaterialTheme.typography.titleLarge,
                    color = amountColor,
                    fontWeight = FontWeight.Bold,
                    fontFamily = FontFamily.Monospace
                )

                Surface(
                    shape = RoundedCornerShape(6.dp),
                    color = MaterialTheme.colorScheme.surface.copy(alpha = 0.6f)
                ) {
                    Text(
                        text = msg.category,
                        style = MaterialTheme.typography.labelSmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                        modifier = Modifier.padding(horizontal = 6.dp, vertical = 3.dp)
                    )
                }
            }

            // Reference / Account / Branch details
            if (!msg.reference.isNullOrEmpty() || !msg.branch.isNullOrEmpty() || !msg.accountTail.isNullOrEmpty()) {
                Row(horizontalArrangement = Arrangement.spacedBy(8.dp), verticalAlignment = Alignment.CenterVertically) {
                    if (!msg.accountTail.isNullOrEmpty()) {
                        Text("Acc: ...${msg.accountTail}", style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                    }
                    if (!msg.reference.isNullOrEmpty()) {
                        Text("Ref: ${msg.reference}", style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                    }
                    if (!msg.branch.isNullOrEmpty()) {
                        Text("Br: ${msg.branch}", style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                    }
                }
            }

            // Footer Timestamp
            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
                Text(fmt(msg.receivedMs), style = MaterialTheme.typography.labelSmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                if (msg.balanceMinor != null) {
                    Text("Bal: Rs. ${msg.balanceMinor / 100.0}", style = MaterialTheme.typography.labelSmall, color = MaterialTheme.colorScheme.primary)
                }
            }
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun MessageDetailSheet(msg: MessageCardItem, onDismiss: () -> Unit, onDelete: () -> Unit) {
    val ctx = androidx.compose.ui.platform.LocalContext.current
    ModalBottomSheet(onDismissRequest = onDismiss) {
        Column(
            Modifier.fillMaxWidth().padding(horizontal = 20.dp, vertical = 12.dp).verticalScroll(rememberScrollState()),
            verticalArrangement = Arrangement.spacedBy(14.dp)
        ) {
            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
                Text(msg.category, style = MaterialTheme.typography.titleLarge)
                IconButton(onClick = onDismiss) { Icon(Icons.Filled.Close, contentDescription = "Close") }
            }

            Card(colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant)) {
                Column(Modifier.padding(14.dp), verticalArrangement = Arrangement.spacedBy(6.dp)) {
                    Text("PARSED DETAILS", style = MaterialTheme.typography.labelSmall, color = MaterialTheme.colorScheme.primary)
                    if (msg.amountMinor != null) {
                        Text("Amount: Rs. ${msg.amountMinor / 100.0} (${msg.direction?.uppercase() ?: "ALERT"})", fontWeight = FontWeight.Bold)
                    }
                    if (msg.balanceMinor != null) {
                        Text("Account Balance: Rs. ${msg.balanceMinor / 100.0}")
                    }
                    if (!msg.accountTail.isNullOrEmpty()) {
                        Text("Account Tail: ...${msg.accountTail}")
                    }
                    if (!msg.reference.isNullOrEmpty()) {
                        Text("Transaction Reference: ${msg.reference}")
                    }
                    if (!msg.branch.isNullOrEmpty()) {
                        Text("Branch / Terminal: ${msg.branch}")
                    }
                    Text("Received: ${fmt(msg.receivedMs)}")
                    Text("Sync State: ${msg.state.uppercase()} ${if (msg.note.isNotEmpty()) "(${msg.note})" else ""}")
                }
            }

            Card(colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface)) {
                Column(Modifier.padding(14.dp), verticalArrangement = Arrangement.spacedBy(6.dp)) {
                    Text("RAW MESSAGE BODY", style = MaterialTheme.typography.labelSmall, color = MaterialTheme.colorScheme.primary)
                    Text(msg.body, style = MaterialTheme.typography.bodyMedium, fontFamily = FontFamily.Monospace)
                }
            }

            if (!msg.receiptUrl.isNullOrEmpty()) {
                Button(
                    onClick = {
                        val intent = Intent(Intent.ACTION_VIEW, Uri.parse(msg.receiptUrl))
                        ctx.startActivity(intent)
                    },
                    modifier = Modifier.fillMaxWidth()
                ) {
                    Icon(Icons.Filled.Receipt, contentDescription = null)
                    Spacer(Modifier.width(8.dp))
                    Text("View Digital Deposit Receipt (vas.combank.net)")
                }
            }

            Spacer(Modifier.height(16.dp))
        }
    }
}

// ==============================================================================================
// 2. ADMIN CONSOLE (Overview, Open Payments Review, WhatsApp Bot Controller, Settings & Logs)
// ==============================================================================================
@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun AdminConsole(store: Store, s: Snap) {
    var adminTab by remember { mutableIntStateOf(0) }

    Column(Modifier.fillMaxSize()) {
        TabRow(selectedTabIndex = adminTab) {
            Tab(selected = adminTab == 0, onClick = { adminTab = 0 }, text = { Text("Overview") })
            Tab(selected = adminTab == 1, onClick = { adminTab = 1 }, text = { Text("Payments") })
            Tab(selected = adminTab == 2, onClick = { adminTab = 2 }, text = { Text("WhatsApp Bot") })
            Tab(selected = adminTab == 3, onClick = { adminTab = 3 }, text = { Text("Settings") })
            Tab(selected = adminTab == 4, onClick = { adminTab = 4 }, text = { Text("Logs") })
        }

        Box(Modifier.fillMaxSize().padding(12.dp)) {
            when (adminTab) {
                0 -> AdminOverviewTab(store, s)
                1 -> AdminPaymentsTab(store)
                2 -> AdminWhatsAppTab(store)
                3 -> AdminSettingsTab(store)
                4 -> AdminLogsTab(s)
            }
        }
    }
}

@Composable
private fun AdminOverviewTab(store: Store, s: Snap) {
    val ctx = androidx.compose.ui.platform.LocalContext.current
    Column(Modifier.fillMaxSize().verticalScroll(rememberScrollState()), verticalArrangement = Arrangement.spacedBy(12.dp)) {
        Card(
            colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant),
            shape = RoundedCornerShape(12.dp)
        ) {
            Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                Text("PayVerify Operational Node", style = MaterialTheme.typography.titleMedium)
                Text("Device ID #${store.deviceId} | ${store.baseUrl}", style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                HorizontalDivider()
                Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                    Column {
                        Text("Outbox Queue", style = MaterialTheme.typography.labelSmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                        Text("${s.queue} pending", style = MaterialTheme.typography.bodyMedium, fontWeight = FontWeight.Bold)
                    }
                    Column(horizontalAlignment = Alignment.End) {
                        Text("Last Sync", style = MaterialTheme.typography.labelSmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                        Text(if (s.lastSync == 0L) "Never" else fmt(s.lastSync), style = MaterialTheme.typography.bodySmall)
                    }
                }
            }
        }

        Button(onClick = { Sync.request(ctx) }, modifier = Modifier.fillMaxWidth()) {
            Icon(Icons.Filled.Sync, contentDescription = null)
            Spacer(Modifier.width(8.dp))
            Text("Sync Pending Messages Now")
        }

        Text("Active Permissions & Battery Exemption", style = MaterialTheme.typography.titleSmall, color = MaterialTheme.colorScheme.primary)
        Card(colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant)) {
            Column(Modifier.padding(12.dp), verticalArrangement = Arrangement.spacedBy(6.dp)) {
                Text("• SMS Interception: ${if (s.sms) "ACTIVE ✅" else "DISABLED ❌"}")
                Text("• Bank App Listener: ${if (s.listener) "ACTIVE ✅" else "DISABLED ❌"}")
                Text("• Battery Optimization Ignored: ${if (s.batteryOk) "YES ✅" else "NO ⚠️"}")
            }
        }
    }
}

@Composable
private fun AdminPaymentsTab(store: Store) {
    val scope = rememberCoroutineScope()
    var claims by remember { mutableStateOf<List<Api.DeviceClaim>?>(null) }
    var busyId by remember { mutableStateOf<Long?>(null) }
    var err by remember { mutableStateOf("") }

    fun refresh() {
        scope.launch {
            try {
                claims = withContext(Dispatchers.IO) { Api.fetchClaims(store) }
            } catch (e: Exception) {
                err = e.message ?: "Could not load claims"
            }
        }
    }

    LaunchedEffect(Unit) { refresh() }

    Column(Modifier.fillMaxSize(), verticalArrangement = Arrangement.spacedBy(10.dp)) {
        Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
            Text("Pending Review Queue", style = MaterialTheme.typography.titleMedium)
            IconButton(onClick = { refresh() }) { Icon(Icons.Filled.Refresh, contentDescription = "Refresh") }
        }

        if (err.isNotEmpty()) {
            Text(err, color = MaterialTheme.colorScheme.error, style = MaterialTheme.typography.bodySmall)
        }

        val list = claims
        if (list == null) {
            Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) { CircularProgressIndicator() }
        } else if (list.isEmpty()) {
            Card(colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant), modifier = Modifier.fillMaxWidth()) {
                Column(Modifier.padding(24.dp), horizontalAlignment = Alignment.CenterHorizontally) {
                    Icon(Icons.Filled.CheckCircle, contentDescription = null, tint = Color(0xFF34D399), modifier = Modifier.size(36.dp))
                    Spacer(Modifier.height(8.dp))
                    Text("No Pending Payments", style = MaterialTheme.typography.titleMedium)
                    Text("All customer orders are verified.", color = MaterialTheme.colorScheme.onSurfaceVariant, style = MaterialTheme.typography.bodySmall)
                }
            }
        } else {
            LazyColumn(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                items(list) { c ->
                    Card(modifier = Modifier.fillMaxWidth(), colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant)) {
                        Column(Modifier.padding(12.dp), verticalArrangement = Arrangement.spacedBy(6.dp)) {
                            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                                Text(c.invoiceNo, fontWeight = FontWeight.Bold)
                                Text(c.amountFormatted, color = MaterialTheme.colorScheme.primary, fontWeight = FontWeight.Bold)
                            }
                            Text(c.customerName ?: c.customerEmail ?: "Customer #${c.id}", style = MaterialTheme.typography.bodySmall)
                            if (!c.reference.isNullOrEmpty()) {
                                Text("Ref: ${c.reference}", style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                            }
                            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
                                Text("Status: ${c.status.uppercase()}", style = MaterialTheme.typography.labelSmall)
                                if (c.status != "verified") {
                                    Button(
                                        enabled = busyId != c.id,
                                        onClick = {
                                            busyId = c.id
                                            scope.launch {
                                                try {
                                                    withContext(Dispatchers.IO) { Api.manuallyVerifyClaim(store, c.id) }
                                                    refresh()
                                                } catch (e: Exception) {
                                                    err = e.message ?: "Verification failed"
                                                } finally {
                                                    busyId = null
                                                }
                                            }
                                        }
                                    ) {
                                        Text(if (busyId == c.id) "Verifying..." else "Quick Verify")
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}

@Composable
private fun AdminWhatsAppTab(store: Store) {
    Card(colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant), modifier = Modifier.fillMaxWidth()) {
        Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
            Text("ComBank WhatsApp Banking Bot", style = MaterialTheme.typography.titleMedium)
            Text("Automated mini-statement (last 3 transactions) synchronization.", style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
            HorizontalDivider()
            Text("Connection: Baileys Linked Device", fontWeight = FontWeight.SemiBold)
            Text("Target Number: ${store.baseUrl}/api/v1/bank-sync/status", style = MaterialTheme.typography.labelSmall)
            Text("WhatsApp Commands: 'last 3', 'status', 'refresh'", style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.primary)
        }
    }
}

@Composable
private fun AdminSettingsTab(store: Store) {
    var bioLock by remember { mutableStateOf(store.biometricLockEnabled) }
    var sendersText by remember { mutableStateOf(store.senders.joinToString(", ")) }
    var savedMsg by remember { mutableStateOf("") }

    Column(Modifier.fillMaxSize().verticalScroll(rememberScrollState()), verticalArrangement = Arrangement.spacedBy(14.dp)) {
        Text("Device Security & Monitoring", style = MaterialTheme.typography.titleMedium)

        Card(colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant)) {
            Column(Modifier.padding(14.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
                    Column {
                        Text("Biometric / PIN App Lock", fontWeight = FontWeight.Bold)
                        Text("Require biometric/PIN to view messages", style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                    }
                    Switch(
                        checked = bioLock,
                        onCheckedChange = {
                            bioLock = it
                            store.biometricLockEnabled = it
                            savedMsg = "App lock setting updated"
                        }
                    )
                }
            }
        }

        Card(colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant)) {
            Column(Modifier.padding(14.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                Text("Monitored Senders & Numbers", fontWeight = FontWeight.Bold)
                Text("Comma-separated bank names, shortcodes, and numbers. (Includes 2662265 for Windows Phone Link)", style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                OutlinedTextField(
                    value = sendersText,
                    onValueChange = { sendersText = it },
                    modifier = Modifier.fillMaxWidth()
                )
                Button(
                    onClick = {
                        val set = sendersText.split(",").map { it.trim() }.filter { it.isNotEmpty() }.toSet()
                        store.senders = set
                        savedMsg = "Monitored sender list saved"
                    }
                ) {
                    Text("Save Monitored Numbers")
                }
            }
        }

        if (savedMsg.isNotEmpty()) {
            Text(savedMsg, color = Color(0xFF34D399), style = MaterialTheme.typography.bodySmall)
        }
    }
}

@Composable
private fun AdminLogsTab(s: Snap) {
    if (s.logs.isEmpty()) {
        Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
            Text("No event logs recorded", color = MaterialTheme.colorScheme.onSurfaceVariant)
        }
    } else {
        LazyColumn(verticalArrangement = Arrangement.spacedBy(6.dp)) {
            items(s.logs) { l ->
                Card(colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant)) {
                    Row(Modifier.padding(10.dp), verticalAlignment = Alignment.CenterVertically) {
                        Text(l.level.uppercase(), style = MaterialTheme.typography.labelSmall, fontWeight = FontWeight.Bold)
                        Spacer(Modifier.width(10.dp))
                        Column(Modifier.weight(1f)) {
                            Text(l.msg, style = MaterialTheme.typography.bodySmall)
                            Text(fmt(l.ts), style = MaterialTheme.typography.labelSmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                        }
                    }
                }
            }
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun EnrolScreen(store: Store, done: () -> Unit) {
    val ctx = androidx.compose.ui.platform.LocalContext.current
    val scope = rememberCoroutineScope()
    var url by remember { mutableStateOf("https://") }
    var code by remember { mutableStateOf("") }
    var name by remember { mutableStateOf(Build.MODEL ?: "Android Phone") }
    var err by remember { mutableStateOf("") }
    var busy by remember { mutableStateOf(false) }

    Scaffold(
        topBar = {
            CenterAlignedTopAppBar(
                title = { Text("PayVerify Enrolment") },
                colors = TopAppBarDefaults.centerAlignedTopAppBarColors(containerColor = MaterialTheme.colorScheme.surface)
            )
        }
    ) { pad ->
        Column(
            Modifier.fillMaxSize().padding(pad).verticalScroll(rememberScrollState()).padding(24.dp),
            verticalArrangement = Arrangement.spacedBy(16.dp)
        ) {
            Text("Connect this relay phone using the code generated in the PayVerify Admin Dashboard under Phones.", style = MaterialTheme.typography.bodySmall)

            OutlinedTextField(value = url, onValueChange = { url = it }, label = { Text("Server Address") }, modifier = Modifier.fillMaxWidth())
            OutlinedTextField(value = code, onValueChange = { code = it.uppercase() }, label = { Text("Enrolment Code") }, modifier = Modifier.fillMaxWidth())
            OutlinedTextField(value = name, onValueChange = { name = it }, label = { Text("Device Name") }, modifier = Modifier.fillMaxWidth())

            if (err.isNotEmpty()) {
                Text(err, color = MaterialTheme.colorScheme.error, style = MaterialTheme.typography.bodySmall)
            }

            Button(
                enabled = !busy && code.trim().length >= 8 && url.trim().startsWith("https://"),
                onClick = {
                    busy = true
                    err = ""
                    scope.launch {
                        try {
                            val id = withContext(Dispatchers.IO) { Api.enrol(url.trim(), code.trim(), name.trim()) }
                            store.baseUrl = url.trim()
                            store.deviceId = id
                            store.log("info", "Device enrolled successfully as ID $id")
                            Sync.schedulePeriodic(ctx)
                            done()
                        } catch (e: Exception) {
                            err = e.message ?: "Could not enrol device"
                        } finally {
                            busy = false
                        }
                    }
                },
                modifier = Modifier.fillMaxWidth()
            ) {
                Text(if (busy) "Connecting..." else "Connect Device")
            }
        }
    }
}

private fun fmt(ms: Long): String = DateFormat.getDateTimeInstance(DateFormat.SHORT, DateFormat.MEDIUM).format(Date(ms))
