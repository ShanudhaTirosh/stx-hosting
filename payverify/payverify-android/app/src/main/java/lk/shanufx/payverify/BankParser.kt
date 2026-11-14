package lk.shanufx.payverify

import java.text.NumberFormat
import java.util.Locale

data class ParsedAlert(
    val amountMinor: Long?,
    val formattedAmount: String,
    val direction: String?, // "credit" or "debit"
    val balanceMinor: Long?,
    val formattedBalance: String?,
    val accountTail: String?,
    val reference: String?,
    val branch: String?,
    val category: String,
    val receiptUrl: String?,
    val isFinancial: Boolean
)

object BankParser {
    private val AMOUNT_REGEX = Regex("""(?:LKR|Rs\.?)\s*([\d,]+(?:\.\d{1,2})?)""", RegexOption.IGNORE_CASE)
    private val CREDIT_REGEX = Regex("""\b(credited|credit|deposit(?:ed)?|received|cr)\b""", RegexOption.IGNORE_CASE)
    private val DEBIT_REGEX = Regex("""\b(debited|debit|withdraw(?:al|n)?|purchase|paid|dr|charge|fee)\b""", RegexOption.IGNORE_CASE)
    private val REF_REGEX = Regex("""(?:ref(?:erence)?(?:\s*(?:no|number|id))?|txn(?:\s*id)?|trx|transaction\s*id|stan)[\s:.#-]*([A-Z0-9]{4,16})""", RegexOption.IGNORE_CASE)
    private val BRANCH_REGEX = Regex("""\bthrough\s+([A-Z][A-Z0-9 .&'-]{1,40}?)[\s-]*(?:CRM\d*|ATM\d*|BR|RM)\b""", RegexOption.IGNORE_CASE)
    private val ACCOUNT_REGEX = Regex("""(?:account|acc(?:\.|ount)?)\s*(?:no\.?)?\s*([A-Za-z0-9*x-]{4,16})""", RegexOption.IGNORE_CASE)
    private val BALANCE_REGEX = Regex("""(?:(?:current|avail(?:able)?|acct?)\s*bal(?:ance)?|bal:?)\s*(?:LKR|Rs\.?)?\s*([\d,]+(?:\.\d{1,2})?)""", RegexOption.IGNORE_CASE)
    private val RECEIPT_URL_REGEX = Regex("""https?://vas\.combank\.net\S*""", RegexOption.IGNORE_CASE)

    fun parse(sender: String, body: String): ParsedAlert {
        var amountMinor: Long? = null
        var formattedAmount = ""
        val mAmount = AMOUNT_REGEX.find(body)
        if (mAmount != null) {
            val rawStr = mAmount.groupValues[1].replace(",", "").trim()
            rawStr.toDoubleOrNull()?.let {
                amountMinor = Math.round(it * 100)
                formattedAmount = "Rs. " + NumberFormat.getNumberInstance(Locale.US).apply {
                    minimumFractionDigits = 2
                    maximumFractionDigits = 2
                }.format(it)
            }
        }

        val hasCredit = CREDIT_REGEX.containsMatchIn(body) || body.contains("CRM Deposit", ignoreCase = true)
        val hasDebit = DEBIT_REGEX.containsMatchIn(body) || body.contains("CRM Withdrawal", ignoreCase = true)

        val direction = if (hasCredit && !hasDebit) {
            "credit"
        } else if (hasDebit && !hasCredit) {
            "debit"
        } else if (hasCredit) {
            "credit"
        } else {
            null
        }

        // Account tail extraction
        var accountTail: String? = null
        ACCOUNT_REGEX.find(body)?.let {
            val raw = it.groupValues[1].replace(Regex("[^0-9]"), "")
            if (raw.length >= 3) {
                accountTail = raw.takeLast(4)
            }
        }

        // Balance extraction
        var balanceMinor: Long? = null
        var formattedBalance: String? = null
        BALANCE_REGEX.find(body)?.let {
            val rawBal = it.groupValues[1].replace(",", "").trim()
            rawBal.toDoubleOrNull()?.let { b ->
                balanceMinor = Math.round(b * 100)
                formattedBalance = "Rs. " + NumberFormat.getNumberInstance(Locale.US).apply {
                    minimumFractionDigits = 2
                    maximumFractionDigits = 2
                }.format(b)
            }
        }

        // Reference extraction
        val ref = REF_REGEX.find(body)?.groupValues?.get(1)?.uppercase()

        // Branch extraction
        var branch: String? = null
        BRANCH_REGEX.find(body)?.let {
            branch = it.groupValues[1].trim().replace(Regex("""\s+"""), " ").uppercase()
        }

        // Receipt URL
        val receiptUrl = RECEIPT_URL_REGEX.find(body)?.value

        // Category determination
        val category = when {
            body.contains("CRM", ignoreCase = true) && direction == "credit" -> "CRM Deposit"
            body.contains("CRM", ignoreCase = true) && direction == "debit" -> "CRM Withdrawal"
            body.contains("ATM", ignoreCase = true) -> "ATM Cash"
            body.contains("CEFTS", ignoreCase = true) || body.contains("TRANSFER", ignoreCase = true) || body.contains("SLR", ignoreCase = true) -> "Fund Transfer"
            body.contains("CARD", ignoreCase = true) || body.contains("POS", ignoreCase = true) -> "Card Payment"
            direction == "credit" -> "Credit Alert"
            direction == "debit" -> "Debit Alert"
            else -> "Bank Alert"
        }

        val isFinancial = amountMinor != null || direction != null

        return ParsedAlert(
            amountMinor = amountMinor,
            formattedAmount = formattedAmount,
            direction = direction,
            balanceMinor = balanceMinor,
            formattedBalance = formattedBalance,
            accountTail = accountTail,
            reference = ref,
            branch = branch,
            category = category,
            receiptUrl = receiptUrl,
            isFinancial = isFinancial
        )
    }

    /** Translates numeric keypad shortcode 2662265 to COMBANK */
    fun normalizeSender(sender: String): String {
        val trimmed = sender.trim()
        if (trimmed == "2662265") return "COMBANK (2662265)"
        return trimmed
    }
}

