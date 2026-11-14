package lk.shanufx.payverify

import android.Manifest
import android.content.Intent
import android.content.pm.PackageManager
import android.os.Build
import android.os.Bundle
import android.os.PowerManager
import android.provider.Settings
import androidx.activity.ComponentActivity
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.compose.setContent
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.unit.dp
import androidx.core.app.NotificationManagerCompat
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import java.text.DateFormat
import java.util.Date

class MainActivity : ComponentActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        val store = App.store(this)
        setContent {
            MaterialTheme(colorScheme = darkColorScheme(
                primary = Color(0xFF3DE0FF),
                onPrimary = Color(0xFF00363F),
                primaryContainer = Color(0xFF004E5B),
                surface = Color(0xFF111418),
                surfaceVariant = Color(0xFF1E232A),
                background = Color(0xFF0B0D10)
            )) {
                Surface(modifier = Modifier.fillMaxSize(), color = MaterialTheme.colorScheme.background) {
                    Root(store)
                }
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
    val events: List<EventRow>,
    val logs: List<LogRow>
)

@OptIn(ExperimentalMaterial3Api::class)
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
        store.history(),
        store.logs()
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
                title = { Text("PayVerify Relay") },
                colors = TopAppBarDefaults.centerAlignedTopAppBarColors(
                    containerColor = MaterialTheme.colorScheme.surface
                )
            )
        }
    ) { pad ->
        Column(
            Modifier
                .fillMaxSize()
                .padding(pad)
                .verticalScroll(rememberScrollState())
                .padding(24.dp),
            verticalArrangement = Arrangement.spacedBy(16.dp)
        ) {
            Card(
                colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant),
                shape = RoundedCornerShape(12.dp)
            ) {
                Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Icon(Icons.Filled.Lock, contentDescription = null, tint = MaterialTheme.colorScheme.primary)
                        Spacer(Modifier.width(8.dp))
                        Text("Connect Relay Phone", style = MaterialTheme.typography.titleMedium)
                    }
                    Text(
                        "Enrol this device using the single-use 8-character code generated in the PayVerify Admin Dashboard under Phones.",
                        style = MaterialTheme.typography.bodySmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant
                    )
                }
            }

            OutlinedTextField(
                value = url,
                onValueChange = { url = it },
                label = { Text("Server Address") },
                leadingIcon = { Icon(Icons.Filled.Cloud, contentDescription = null) },
                singleLine = true,
                modifier = Modifier.fillMaxWidth()
            )

            OutlinedTextField(
                value = code,
                onValueChange = { code = it.uppercase() },
                label = { Text("Enrolment Code") },
                leadingIcon = { Icon(Icons.Filled.Key, contentDescription = null) },
                singleLine = true,
                modifier = Modifier.fillMaxWidth()
            )

            OutlinedTextField(
                value = name,
                onValueChange = { name = it },
                label = { Text("Device Name") },
                leadingIcon = { Icon(Icons.Filled.PhoneAndroid, contentDescription = null) },
                singleLine = true,
                modifier = Modifier.fillMaxWidth()
            )

            if (err.isNotEmpty()) {
                Card(
                    colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.errorContainer),
                    shape = RoundedCornerShape(8.dp)
                ) {
                    Row(Modifier.padding(12.dp), verticalAlignment = Alignment.CenterVertically) {
                        Icon(Icons.Filled.Warning, contentDescription = null, tint = MaterialTheme.colorScheme.error)
                        Spacer(Modifier.width(8.dp))
                        Text(err, color = MaterialTheme.colorScheme.onErrorContainer, style = MaterialTheme.typography.bodyMedium)
                    }
                }
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
                if (busy) {
                    CircularProgressIndicator(modifier = Modifier.size(18.dp), strokeWidth = 2.dp, color = MaterialTheme.colorScheme.onPrimary)
                    Spacer(Modifier.width(8.dp))
                    Text("Connecting...")
                } else {
                    Icon(Icons.Filled.Link, contentDescription = null)
                    Spacer(Modifier.width(8.dp))
                    Text("Connect Device")
                }
            }
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun MainScreen(store: Store, s: Snap) {
    var tab by remember { mutableIntStateOf(0) }

    Scaffold(
        topBar = {
            CenterAlignedTopAppBar(
                title = {
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Icon(Icons.Filled.Shield, contentDescription = null, tint = MaterialTheme.colorScheme.primary)
                        Spacer(Modifier.width(8.dp))
                        Text("PayVerify")
                        Spacer(Modifier.width(8.dp))
                        Surface(
                            shape = RoundedCornerShape(12.dp),
                            color = if (s.err.isEmpty()) Color(0xFF1B3D2F) else Color(0xFF4A1B1B)
                        ) {
                            Row(
                                modifier = Modifier.padding(horizontal = 8.dp, vertical = 2.dp),
                                verticalAlignment = Alignment.CenterVertically
                            ) {
                                Icon(
                                    imageVector = if (s.err.isEmpty()) Icons.Filled.CheckCircle else Icons.Filled.Warning,
                                    contentDescription = null,
                                    tint = if (s.err.isEmpty()) Color(0xFF34D399) else Color(0xFFF87171),
                                    modifier = Modifier.size(12.dp)
                                )
                                Spacer(Modifier.width(4.dp))
                                Text(
                                    text = if (s.err.isEmpty()) "ONLINE" else "ERROR",
                                    style = MaterialTheme.typography.labelSmall,
                                    color = if (s.err.isEmpty()) Color(0xFF34D399) else Color(0xFFF87171)
                                )
                            }
                        }
                    }
                },
                colors = TopAppBarDefaults.centerAlignedTopAppBarColors(
                    containerColor = MaterialTheme.colorScheme.surface
                )
            )
        },
        bottomBar = {
            NavigationBar(
                containerColor = MaterialTheme.colorScheme.surfaceVariant
            ) {
                val navItems = listOf(
                    Triple("Status", Icons.Filled.Dashboard, 0),
                    Triple("Payments", Icons.Filled.Payments, 1),
                    Triple("Alerts", Icons.Filled.Sms, 2),
                    Triple("Logs", Icons.Filled.History, 3)
                )
                navItems.forEach { (label, icon, index) ->
                    NavigationBarItem(
                        selected = tab == index,
                        onClick = { tab = index },
                        icon = { Icon(icon, contentDescription = label) },
                        label = { Text(label) }
                    )
                }
            }
        }
    ) { pad ->
        Box(
            Modifier
                .fillMaxSize()
                .padding(pad)
                .padding(16.dp)
        ) {
            when (tab) {
                0 -> StatusTab(store, s)
                1 -> PaymentsTab(store)
                2 -> AlertsTab(s)
                3 -> LogsTab(s)
            }
        }
    }
}

@Composable
private fun StatusTab(store: Store, s: Snap) {
    val ctx = androidx.compose.ui.platform.LocalContext.current
    val askSms = rememberLauncherForActivityResult(ActivityResultContracts.RequestPermission()) {}
    var confirmReset by remember { mutableStateOf(false) }

    Column(
        Modifier
            .fillMaxSize()
            .verticalScroll(rememberScrollState()),
        verticalArrangement = Arrangement.spacedBy(14.dp)
    ) {
        // Telemetry Summary Card
        Card(
            colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant),
            shape = RoundedCornerShape(12.dp)
        ) {
            Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
                    Text("Device ID #${store.deviceId}", style = MaterialTheme.typography.titleMedium)
                    FilledTonalButton(onClick = { Sync.request(ctx) }) {
                        Icon(Icons.Filled.Sync, contentDescription = null, modifier = Modifier.size(16.dp))
                        Spacer(Modifier.width(6.dp))
                        Text("Sync Now")
                    }
                }
                Text("Server: ${store.baseUrl}", style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                HorizontalDivider(color = MaterialTheme.colorScheme.surface.copy(alpha = 0.5f))
                Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                    Column {
                        Text("Last Upload", style = MaterialTheme.typography.labelSmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                        Text(if (s.lastSync == 0L) "Never" else fmt(s.lastSync), style = MaterialTheme.typography.bodySmall)
                    }
                    Column(horizontalAlignment = Alignment.End) {
                        Text("Queue Depth", style = MaterialTheme.typography.labelSmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                        Text("${s.queue} pending", style = MaterialTheme.typography.bodySmall)
                    }
                }
            }
        }

        if (s.err.isNotEmpty()) {
            Card(
                colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.errorContainer),
                shape = RoundedCornerShape(8.dp)
            ) {
                Row(Modifier.padding(12.dp), verticalAlignment = Alignment.CenterVertically) {
                    Icon(Icons.Filled.Warning, contentDescription = null, tint = MaterialTheme.colorScheme.error)
                    Spacer(Modifier.width(8.dp))
                    Text(s.err, color = MaterialTheme.colorScheme.onErrorContainer, style = MaterialTheme.typography.bodyMedium)
                }
            }
        }

        Text("Permissions & Background Services", style = MaterialTheme.typography.titleSmall, color = MaterialTheme.colorScheme.primary)

        PermissionCard(
            title = "Bank SMS Interception",
            subtitle = "Listens to incoming Commercial Bank transaction SMS",
            icon = Icons.Filled.Sms,
            enabled = s.sms,
            actionLabel = "Allow SMS",
            onAction = { askSms.launch(Manifest.permission.RECEIVE_SMS) }
        )

        PermissionCard(
            title = "Bank App Notifications",
            subtitle = "Captures transaction alerts from ComBank Digital app",
            icon = Icons.Filled.NotificationsActive,
            enabled = s.listener,
            actionLabel = "Settings",
            onAction = { ctx.startActivity(Intent(Settings.ACTION_NOTIFICATION_LISTENER_SETTINGS)) }
        )

        PermissionCard(
            title = "Background Running",
            subtitle = "Bypasses Android battery optimization for real-time alerts",
            icon = Icons.Filled.PowerSettingsNew,
            enabled = s.batteryOk,
            actionLabel = "Fix",
            onAction = { ctx.startActivity(Intent(Settings.ACTION_IGNORE_BATTERY_OPTIMIZATION_SETTINGS)) }
        )

        Spacer(Modifier.height(8.dp))

        OutlinedButton(
            onClick = { confirmReset = true },
            colors = ButtonDefaults.outlinedButtonColors(contentColor = MaterialTheme.colorScheme.error),
            modifier = Modifier.fillMaxWidth()
        ) {
            Icon(Icons.Filled.LinkOff, contentDescription = null)
            Spacer(Modifier.width(8.dp))
            Text("Disconnect This Device")
        }
    }

    if (confirmReset) {
        AlertDialog(
            onDismissRequest = { confirmReset = false },
            title = { Text("Disconnect Relay Phone?") },
            text = { Text("Alerts will immediately stop reaching the PayVerify server. Reconnecting will require generating a new one-time enrolment code.") },
            confirmButton = {
                TextButton(onClick = {
                    Signer.forget()
                    store.resetEnrolment()
                    confirmReset = false
                }) {
                    Text("Disconnect", color = MaterialTheme.colorScheme.error)
                }
            },
            dismissButton = {
                TextButton(onClick = { confirmReset = false }) {
                    Text("Cancel")
                }
            }
        )
    }
}

@Composable
private fun PermissionCard(
    title: String,
    subtitle: String,
    icon: ImageVector,
    enabled: Boolean,
    actionLabel: String,
    onAction: () -> Unit
) {
    Card(
        modifier = Modifier.fillMaxWidth(),
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant),
        shape = RoundedCornerShape(10.dp)
    ) {
        Row(
            Modifier.padding(14.dp),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.CenterVertically
        ) {
            Row(Modifier.weight(1f), verticalAlignment = Alignment.CenterVertically) {
                Icon(icon, contentDescription = null, tint = if (enabled) MaterialTheme.colorScheme.primary else Color(0xFFF87171))
                Spacer(Modifier.width(12.dp))
                Column {
                    Text(title, style = MaterialTheme.typography.titleSmall)
                    Text(subtitle, style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                    Spacer(Modifier.height(4.dp))
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Icon(
                            imageVector = if (enabled) Icons.Filled.CheckCircle else Icons.Filled.Cancel,
                            contentDescription = null,
                            tint = if (enabled) Color(0xFF34D399) else Color(0xFFF87171),
                            modifier = Modifier.size(14.dp)
                        )
                        Spacer(Modifier.width(4.dp))
                        Text(
                            text = if (enabled) "Active" else "Disabled",
                            style = MaterialTheme.typography.labelSmall,
                            color = if (enabled) Color(0xFF34D399) else Color(0xFFF87171)
                        )
                    }
                }
            }
            if (!enabled) {
                OutlinedButton(onClick = onAction) {
                    Text(actionLabel)
                }
            }
        }
    }
}

@Composable
private fun PaymentsTab(store: Store) {
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

    Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
        Row(
            Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.CenterVertically
        ) {
            Text("Pending Payments", style = MaterialTheme.typography.titleMedium)
            IconButton(onClick = { refresh() }) {
                Icon(Icons.Filled.Refresh, contentDescription = "Refresh")
            }
        }

        if (err.isNotEmpty()) {
            Card(colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.errorContainer)) {
                Row(Modifier.padding(12.dp), verticalAlignment = Alignment.CenterVertically) {
                    Icon(Icons.Filled.Warning, contentDescription = null, tint = MaterialTheme.colorScheme.error)
                    Spacer(Modifier.width(8.dp))
                    Text(err, color = MaterialTheme.colorScheme.onErrorContainer, style = MaterialTheme.typography.bodySmall)
                }
            }
        }

        val list = claims
        if (list == null) {
            Box(Modifier.fillMaxWidth().padding(32.dp), contentAlignment = Alignment.Center) {
                CircularProgressIndicator()
            }
        } else if (list.isEmpty()) {
            Card(
                colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant),
                modifier = Modifier.fillMaxWidth()
            ) {
                Column(Modifier.padding(24.dp), horizontalAlignment = Alignment.CenterHorizontally) {
                    Icon(Icons.Filled.CheckCircle, contentDescription = null, tint = Color(0xFF34D399), modifier = Modifier.size(36.dp))
                    Spacer(Modifier.height(8.dp))
                    Text("No Open Payments", style = MaterialTheme.typography.titleMedium)
                    Text("All customer orders are verified or up to date.", color = MaterialTheme.colorScheme.onSurfaceVariant, style = MaterialTheme.typography.bodySmall)
                }
            }
        } else {
            LazyColumn(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                items(list) { c ->
                    Card(
                        modifier = Modifier.fillMaxWidth(),
                        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant),
                        shape = RoundedCornerShape(10.dp)
                    ) {
                        Column(Modifier.padding(14.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
                                Text(c.invoiceNo, style = MaterialTheme.typography.titleMedium)
                                Text(c.amountFormatted, color = MaterialTheme.colorScheme.primary, style = MaterialTheme.typography.titleMedium)
                            }
                            val custText = c.customerName ?: c.customerEmail ?: "Customer #${c.customerId ?: c.id}"
                            Row(verticalAlignment = Alignment.CenterVertically) {
                                Icon(Icons.Filled.Person, contentDescription = null, modifier = Modifier.size(14.dp), tint = MaterialTheme.colorScheme.onSurfaceVariant)
                                Spacer(Modifier.width(4.dp))
                                Text(custText, style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                            }
                            if (c.reference != null) {
                                Row(verticalAlignment = Alignment.CenterVertically) {
                                    Icon(Icons.Filled.Tag, contentDescription = null, modifier = Modifier.size(14.dp), tint = MaterialTheme.colorScheme.onSurfaceVariant)
                                    Spacer(Modifier.width(4.dp))
                                    Text("Ref: ${c.reference}", style = MaterialTheme.typography.bodySmall)
                                }
                            }
                            Row(
                                Modifier.fillMaxWidth(),
                                horizontalArrangement = Arrangement.SpaceBetween,
                                verticalAlignment = Alignment.CenterVertically
                            ) {
                                Surface(
                                    shape = RoundedCornerShape(6.dp),
                                    color = if (c.status == "verified") Color(0xFF1B3D2F) else Color(0xFF3B2F1B)
                                ) {
                                    Text(
                                        text = c.status.uppercase(),
                                        style = MaterialTheme.typography.labelSmall,
                                        color = if (c.status == "verified") Color(0xFF34D399) else Color(0xFFFBBF24),
                                        modifier = Modifier.padding(horizontal = 8.dp, vertical = 3.dp)
                                    )
                                }

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
                                        if (busyId == c.id) {
                                            CircularProgressIndicator(modifier = Modifier.size(16.dp), strokeWidth = 2.dp, color = MaterialTheme.colorScheme.onPrimary)
                                            Spacer(Modifier.width(6.dp))
                                            Text("Verifying...")
                                        } else {
                                            Icon(Icons.Filled.Check, contentDescription = null, modifier = Modifier.size(16.dp))
                                            Spacer(Modifier.width(6.dp))
                                            Text("Verify")
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
}

@Composable
private fun AlertsTab(s: Snap) {
    Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
        Text("Captured Bank Alerts", style = MaterialTheme.typography.titleMedium)
        if (s.events.isEmpty()) {
            Card(
                colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant),
                modifier = Modifier.fillMaxWidth()
            ) {
                Column(Modifier.padding(24.dp), horizontalAlignment = Alignment.CenterHorizontally) {
                    Icon(Icons.Filled.Sms, contentDescription = null, tint = MaterialTheme.colorScheme.onSurfaceVariant, modifier = Modifier.size(36.dp))
                    Spacer(Modifier.height(8.dp))
                    Text("No Bank Alerts Yet", style = MaterialTheme.typography.titleSmall)
                    Text("Incoming Commercial Bank SMS will appear here automatically.", color = MaterialTheme.colorScheme.onSurfaceVariant, style = MaterialTheme.typography.bodySmall)
                }
            }
        } else {
            LazyColumn(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                items(s.events) { e ->
                    Card(
                        modifier = Modifier.fillMaxWidth(),
                        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant),
                        shape = RoundedCornerShape(8.dp)
                    ) {
                        Column(Modifier.padding(12.dp), verticalArrangement = Arrangement.spacedBy(4.dp)) {
                            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
                                Row(verticalAlignment = Alignment.CenterVertically) {
                                    Icon(
                                        imageVector = if (e.source == "sms") Icons.Filled.Sms else Icons.Filled.Notifications,
                                        contentDescription = null,
                                        tint = MaterialTheme.colorScheme.primary,
                                        modifier = Modifier.size(16.dp)
                                    )
                                    Spacer(Modifier.width(6.dp))
                                    Text(e.sender, style = MaterialTheme.typography.titleSmall)
                                }
                                Row(verticalAlignment = Alignment.CenterVertically) {
                                    Icon(
                                        imageVector = if (e.state == "pending") Icons.Filled.CloudUpload else Icons.Filled.CloudDone,
                                        contentDescription = null,
                                        tint = if (e.state == "pending") Color(0xFFFBBF24) else Color(0xFF34D399),
                                        modifier = Modifier.size(14.dp)
                                    )
                                    Spacer(Modifier.width(4.dp))
                                    Text(
                                        text = if (e.state == "pending") "Queued" else "Uploaded",
                                        style = MaterialTheme.typography.labelSmall,
                                        color = if (e.state == "pending") Color(0xFFFBBF24) else Color(0xFF34D399)
                                    )
                                }
                            }
                            Text(fmt(e.ts), style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                            if (e.note.isNotEmpty()) {
                                Text("Server Note: ${e.note}", style = MaterialTheme.typography.bodySmall)
                            }
                        }
                    }
                }
            }
        }
    }
}

@Composable
private fun LogsTab(s: Snap) {
    Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
        Text("Sync Event Logs", style = MaterialTheme.typography.titleMedium)
        if (s.logs.isEmpty()) {
            Card(
                colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant),
                modifier = Modifier.fillMaxWidth()
            ) {
                Column(Modifier.padding(24.dp), horizontalAlignment = Alignment.CenterHorizontally) {
                    Icon(Icons.Filled.History, contentDescription = null, tint = MaterialTheme.colorScheme.onSurfaceVariant, modifier = Modifier.size(36.dp))
                    Spacer(Modifier.height(8.dp))
                    Text("No Event Logs", style = MaterialTheme.typography.titleSmall)
                    Text("Background events and relay activities will appear here.", color = MaterialTheme.colorScheme.onSurfaceVariant, style = MaterialTheme.typography.bodySmall)
                }
            }
        } else {
            LazyColumn(verticalArrangement = Arrangement.spacedBy(6.dp)) {
                items(s.logs) { l ->
                    val badgeColor = when (l.level.lowercase()) {
                        "error" -> Color(0xFFF87171)
                        "warn" -> Color(0xFFFBBF24)
                        else -> Color(0xFF34D399)
                    }
                    val badgeBg = when (l.level.lowercase()) {
                        "error" -> Color(0xFF4A1B1B)
                        "warn" -> Color(0xFF3B2F1B)
                        else -> Color(0xFF1B3D2F)
                    }
                    Card(
                        modifier = Modifier.fillMaxWidth(),
                        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant),
                        shape = RoundedCornerShape(6.dp)
                    ) {
                        Row(Modifier.padding(10.dp), verticalAlignment = Alignment.CenterVertically) {
                            Surface(shape = RoundedCornerShape(4.dp), color = badgeBg) {
                                Text(
                                    text = l.level.uppercase(),
                                    style = MaterialTheme.typography.labelSmall,
                                    color = badgeColor,
                                    modifier = Modifier.padding(horizontal = 6.dp, vertical = 2.dp)
                                )
                            }
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
}

private fun fmt(ms: Long): String = DateFormat.getDateTimeInstance(DateFormat.SHORT, DateFormat.MEDIUM).format(Date(ms))
