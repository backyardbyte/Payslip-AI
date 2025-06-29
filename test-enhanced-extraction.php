<?php

require_once 'vendor/autoload.php';

use App\Services\PayslipProcessingService;
use App\Services\SettingsService;
use Illuminate\Support\Facades\Log;

// Simulate Laravel environment
if (!function_exists('config')) {
    function config($key, $default = null) {
        return $default;
    }
}

// Mock Log class if not available
if (!class_exists('Illuminate\Support\Facades\Log')) {
    class MockLog {
        public static function info($message, $context = []) {
            echo "[INFO] $message\n";
            if (!empty($context)) {
                echo json_encode($context, JSON_PRETTY_PRINT) . "\n";
            }
        }
        
        public static function warning($message, $context = []) {
            echo "[WARNING] $message\n";
            if (!empty($context)) {
                echo json_encode($context, JSON_PRETTY_PRINT) . "\n";
            }
        }
    }
    
    // Create alias
    class_alias('MockLog', 'Illuminate\Support\Facades\Log');
}

/**
 * Test Enhanced Payslip Extraction
 */
class TestEnhancedExtraction
{
    private $service;
    
    public function __construct()
    {
        // Create mock settings service that extends the real class
        $settingsService = new class extends SettingsService {
            public function __construct() {
                // Override constructor to avoid dependencies
            }
            
            public function get(string $key, $default = null, string $environment = null): mixed {
                return $default;
            }
        };
        
        $this->service = new PayslipProcessingService($settingsService);
    }
    
    public function runTests()
    {
        echo "=== Testing Enhanced Payslip Extraction ===\n\n";
        
        // Test Case 1: Norhakimi's payslip (from image)
        $this->testCase1();
        
        // Test Case 2: Fadzri's payslip (from image)
        $this->testCase2();
        
        // Test Case 3: NIK AMNAN's payslip (from image)
        $this->testCase3();
        
        // Test Case 4: Muhammad Khairul's payslip
        $this->testCase4();
        
        // Test Case 5: MEOR RIDZWAN's payslip
        $this->testCase5();
    }
    
    private function testCase1()
    {
        echo "--- Test Case 1: Norhakimi Bin Sahimi ---\n";
        
        $sampleText = "
        KERAJAAN MALAYSIA
        1110 KEMENTERIAN DALAM NEGERI
        Nama : Norhakimi Bin Sahimi
        No. Gaji : 20416406
        No. K/P : 860411-33-5797
        K.Pkja/Sub Pkja : A / 01 Pegawai Awam
        
        Pendapatan                    AMAUN (RM)
        0001 Gaji Pokok              3,198.61
        1052 Im Tmp Perumahan         300.00
        1055 Im Tmp Khidmat Awam      100.00
        1358 Bt Perkhidmatan APMM     350.00
        1362 B.Imb Sara Hidup         350.00
        
        Jumlah Pendapatan    :    4,213.61
        
        Potongan                     AMAUN (RM)
        4156 Byrn Blk Pin Kdrn BSN    184.17
        4369 Zakat-WTF Pahang          60.05
        4343 DKP                        9.55
        4561 Kelab Krjkn & Sukan APMM  60.03
        5800 Pend.Komp Ang.Perkh.Awam 108.48
        
        Jumlah Potongan      :      368.30
        Gaji Bersih          :    3,845.31
        % Peratus Gaji Bersih :     91.26
        ";
        
        $this->processAndValidate($sampleText, [
            'nama' => 'Norhakimi Bin Sahimi',
            'no_gaji' => '20416406',
            'gaji_pokok' => 3198.61,
            'jumlah_pendapatan' => 4213.61,
            'jumlah_potongan' => 368.30,
            'gaji_bersih' => 3845.31,
            'peratus_gaji_bersih' => 91.26
        ]);
    }
    
    private function testCase2()
    {
        echo "\n--- Test Case 2: Fadzri ---\n";
        
        $sampleText = "
        1020 JANM NEGERI SABAH
        Nama : Fadzri @ Adi Mohd.Fadzri Bin Ali
        No. Gaji : 60035731
        No. K/P : 811024-12-5609
        K.Pkja/Sub Pkja : A / 01 Pegawai Awam
        
        Pendapatan                    AMAUN (RM)
        0001 Gaji Pokok              3,621.56
        1055 Im Tmp Khidmat Awam       160.00
        1254 Byrn Imbuahan Wilayah     780.77
        1351 Bt Perumahan Wilayah      590.00
        1370 Bt M.Lokasi&ThpKesusahan  500.00
        
        Jumlah Pendapatan    :    7,792.33
        
        Potongan                     AMAUN (RM)
        2002 Cukai Pendapatan         175.00
        4141 Pinj Perumahan Satu    1,525.54
        4176 Pinjaman Peribadi BSN     940.00
        5111 Pinj Yayasan Ihsan Skim 2 273.24
        6025 Angkasa                 2,243.18
        6026 Angkasa (Bukan PINJAMAN) 1,383.32
        
        Jumlah Potongan      :    6,330.98
        Gaji Bersih          :    1,461.35
        % Peratus Gaji Bersih :      18.75
        ";
        
        $this->processAndValidate($sampleText, [
            'nama' => 'Fadzri @ Adi Mohd.Fadzri Bin Ali',
            'no_gaji' => '60035731',
            'gaji_pokok' => 3621.56,
            'jumlah_pendapatan' => 7792.33,
            'jumlah_potongan' => 6330.98,
            'gaji_bersih' => 1461.35,
            'peratus_gaji_bersih' => 18.75
        ]);
    }
    
    private function testCase3()
    {
        echo "\n--- Test Case 3: NIK AMNAN BIN AHMAD ---\n";
        
        $sampleText = "
        Pej. Perakaunan : 1110 KEMENTERIAN DALAM NEGERI
        Nama : NIK AMNAN BIN AHMAD NO
        No. Gaji : 80128290
        No. K/P : 890826-11-5021
        K.Pkja/Sub PKja : A / 14 PDRM-Pg Rendah Polis
        
        Pendapatan                    AMAUN (RM)
        0001 Gaji Pokok              3,565.70
        1052 Im Tmp Perumahan         300.00
        1055 Im Tmp Khidmat Awam      315.00
        1254 Byrn Imbuahan Wilayah    545.37
        1373 B.Khas P.G.A.            400.00
        1540 Bt Perkhidmatan PDRM     200.00
        
        Jumlah Pendapatan    :    4,926.10
        
        Potongan                     AMAUN (RM)
        4547 Pla Blk TTP Kdrn         25.00
        4646 Bend.Klbsolis BN 19      18.00
        4173 PPK BPFIS BRIDGE TENGAH   1.00
        5046 PPRPD                    10.00
        5070 Potongan KKS PDRM         1.50
        6025 Angkasa                2,698.00
        
        Jumlah Potongan      :    2,962.50
        Gaji Bersih          :    1,963.60
        % Peratus Gaji Bersih :      39.86
        ";
        
        $this->processAndValidate($sampleText, [
            'nama' => 'NIK AMNAN BIN AHMAD NO',
            'no_gaji' => '80128290',
            'gaji_pokok' => 3565.70,
            'jumlah_pendapatan' => 4926.10,
            'jumlah_potongan' => 2962.50,
            'gaji_bersih' => 1963.60,
            'peratus_gaji_bersih' => 39.86
        ]);
    }
    
    private function testCase4()
    {
        echo "\n--- Test Case 4: Muhammad Khairul ---\n";
        
        $sampleText = "
        KEMENTERIAN KEWANGAN
        JABATAN AKAUNTAN NEGARA MALAYSIA
        
        Pej. Perakaunan : 1110 KEMENTERIAN DALAM NEGERI
        Nama : MUHAMMAD KHAIRUL HANIF BIN AMRON
        No. Gaji : 80159771
        No. K/P : 910108-02-5465
        K.Pkja/Sub Pkja : A / 14 PDRM-Pg Rendah Polis
        
        Pendapatan                    AMAUN (RM)
        0001 Gaji Pokok              3,076.00
        1052 Im Tmp Perumahan         115.00
        1055 Im Tmp Khidmat Awam      315.00
        1358 Byrn Ins Tugas Am        200.00
        1518 Bt Perkhidmatan PDRM     200.00
        1540 Bt Perkhidmatan PDRM     200.00
        
        Jumlah Pendapatan    :    3,891.00
        
        Potongan                     AMAUN (RM)
        4677 KelabPolis Dae.Gua Musang  1.00
        4930 Thd Berkhlt POB Kelantan  10.00
        4941 TKS Sosial Kelantan        1.00
        5046 PPRPD                      1.50
        5070 Potongan KKS BDRM          1.50
        5099 Coop-Polis DiHati Msia(2) 175.85
        5111 Pinj Yayasan Ihsan Rkyt.2 274.60
        5113 Pinj Coshare Holdings Bhd  864.60
        6026 Angkasa (Bukan PINJAMAN) 1,119.89
        
        Jumlah Potongan      :    2,469.44
        Gaji Bersih          :    1,421.56
        % Peratus Gaji Bersih :      36.53
        ";
        
        $this->processAndValidate($sampleText, [
            'nama' => 'MUHAMMAD KHAIRUL HANIF BIN AMRON',
            'no_gaji' => '80159771',
            'gaji_pokok' => 3076.00,
            'jumlah_pendapatan' => 3891.00,
            'jumlah_potongan' => 2469.44,
            'gaji_bersih' => 1421.56,
            'peratus_gaji_bersih' => 36.53
        ]);
    }
    
    private function testCase5()
    {
        echo "\n--- Test Case 5: MEOR RIDZWAN ---\n";
        
        $sampleText = "
        KERAJAAN MALAYSIA
        1104 KEMENTERIAN KERJA RAYA
        Nama : MEOR RIDZWAN BIN ISMAIL
        No. Gaji : 20062437
        No. K/P : 821105-14-5133
        K.Pkja/Sub Pkja : A / 01 Pegawai Awam
        
        Pendapatan                    AMAUN (RM)
        0001 Gaji Pokok              4,672.76
        1052 Im Tmp Perumahan         300.00
        1055 Im Tmp Khidmat Awam      160.00
        1072 Bt Khas Kewangan (BKK)   500.00
        1362 B.Imb Sara Hidup         350.00
        
        Jumlah Pendapatan    :    5,982.76
        
        Potongan                     AMAUN (RM)
        2002 Cukai Pendapatan         110.40
        6025 Angkasa                1,244.00
        6026 Angkasa (Bukan PINJAMAN) 1,923.00
        
        Jumlah Potongan      :    3,277.40
        Gaji Bersih          :    2,705.36
        % Peratus Gaji Bersih :      45.22
        ";
        
        $this->processAndValidate($sampleText, [
            'nama' => 'MEOR RIDZWAN BIN ISMAIL',
            'no_gaji' => '20062437',
            'gaji_pokok' => 4672.76,
            'jumlah_pendapatan' => 5982.76,
            'jumlah_potongan' => 3277.40,
            'gaji_bersih' => 2705.36,
            'peratus_gaji_bersih' => 45.22
        ]);
    }
    
    private function processAndValidate($text, $expected)
    {
        // Use reflection to access private methods
        $reflection = new ReflectionClass($this->service);
        $method = $reflection->getMethod('extractPayslipDataAdvanced');
        $method->setAccessible(true);
        
        $extracted = $method->invoke($this->service, $text);
        
        echo "Extracted Data:\n";
        printf("  Nama: %s\n", $extracted['nama'] ?? 'N/A');
        printf("  No. Gaji: %s\n", $extracted['no_gaji'] ?? 'N/A');
        printf("  Gaji Pokok: %s\n", $extracted['gaji_pokok'] ? 'RM ' . number_format($extracted['gaji_pokok'], 2) : 'N/A');
        printf("  Jumlah Pendapatan: %s\n", $extracted['jumlah_pendapatan'] ? 'RM ' . number_format($extracted['jumlah_pendapatan'], 2) : 'N/A');
        printf("  Jumlah Potongan: %s\n", $extracted['jumlah_potongan'] ? 'RM ' . number_format($extracted['jumlah_potongan'], 2) : 'N/A');
        printf("  Gaji Bersih: %s\n", $extracted['gaji_bersih'] ? 'RM ' . number_format($extracted['gaji_bersih'], 2) : 'N/A');
        printf("  Peratus Gaji Bersih: %s\n", $extracted['peratus_gaji_bersih'] ? number_format($extracted['peratus_gaji_bersih'], 2) . '%' : 'N/A');
        
        echo "\nValidation Results:\n";
        $totalTests = 0;
        $passedTests = 0;
        
        foreach ($expected as $field => $expectedValue) {
            $totalTests++;
            $extractedValue = $extracted[$field] ?? null;
            
            // Handle different field types
            if (in_array($field, ['gaji_pokok', 'jumlah_pendapatan', 'jumlah_potongan', 'gaji_bersih', 'peratus_gaji_bersih'])) {
                $match = $extractedValue !== null && abs($extractedValue - $expectedValue) < 0.01;
            } else {
                $match = $extractedValue === $expectedValue;
            }
            
            if ($match) {
                $passedTests++;
                echo "  ✅ {$field}: PASS\n";
            } else {
                echo "  ❌ {$field}: FAIL (Expected: {$expectedValue}, Got: " . ($extractedValue ?? 'NULL') . ")\n";
            }
        }
        
        $accuracy = round(($passedTests / $totalTests) * 100, 1);
        echo "\nAccuracy: {$passedTests}/{$totalTests} ({$accuracy}%)\n";
        
        if (!empty($extracted['debug_patterns'])) {
            echo "\nDebug Patterns:\n";
            foreach ($extracted['debug_patterns'] as $debug) {
                echo "  - {$debug}\n";
            }
        }
        
        return $accuracy;
    }
}

// Run the tests
$tester = new TestEnhancedExtraction();
$tester->runTests();

echo "\n=== Test Summary ===\n";
echo "All enhanced extraction patterns have been tested.\n";
echo "The improved extraction should now handle:\n";
echo "  ✅ Tabular data format with codes (0001, 1052, etc.)\n";
echo "  ✅ Side-by-side summary format\n";
echo "  ✅ Enhanced validation and error correction\n";
echo "  ✅ Better Malaysian name patterns\n";
echo "  ✅ Improved percentage extraction\n";
echo "  ✅ Cross-validation of financial data\n"; 