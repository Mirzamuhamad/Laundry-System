<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Expense;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

class ReportExportController extends Controller
{
    public function __invoke(Request $request)
    {
        $filters = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'outlet_id' => ['nullable', 'integer', 'exists:outlets,id'],
        ]);
        $outletId = $filters['outlet_id'] ?? null;
        $allOrders = Order::with(['outlet', 'user'])->whereBetween('created_at', [$filters['from'].' 00:00:00', $filters['to'].' 23:59:59'])->when($outletId, fn ($query) => $query->where('outlet_id', $outletId));
        $validOrders = (clone $allOrders)->where('status', '!=', 'cancelled');
        $payments = Payment::whereBetween('paid_at', [$filters['from'].' 00:00:00', $filters['to'].' 23:59:59'])->when($outletId, fn ($query) => $query->whereHas('order', fn ($order) => $order->where('outlet_id', $outletId)));
        $expenses = Expense::with(['outlet', 'user'])
            ->whereDate('expense_date', '>=', $filters['from'])
            ->whereDate('expense_date', '<=', $filters['to'])
            ->when($outletId, fn ($query) => $query->where('outlet_id', $outletId));
        $attendances = Attendance::with(['user.schedule', 'outlet'])
            ->whereDate('attendance_date', '>=', $filters['from'])
            ->whereDate('attendance_date', '<=', $filters['to'])
            ->when($outletId, fn ($query) => $query->where('outlet_id', $outletId));

        $sales = (clone $validOrders)->sum('total');
        $received = (clone $payments)->sum('amount');
        $expenseTotal = (clone $expenses)->sum('amount');
        $receivables = (clone $validOrders)->selectRaw('COALESCE(SUM(total - paid_amount), 0) AS balance')->value('balance');

        $directory = storage_path('app/private/exports');
        File::ensureDirectoryExists($directory);
        $filename = 'laporan-laundry-'.$filters['from'].'-'.$filters['to'].'.xlsx';
        $path = $directory.'/'.uniqid('report-', true).'.xlsx';
        $writer = new Writer;
        $writer->openToFile($path);

        $titleStyle = new Style(fontBold: true, fontSize: 16, fontColor: '0F766E');
        $headerStyle = new Style(fontBold: true, fontColor: 'FFFFFF', backgroundColor: '0F766E');
        $labelStyle = new Style(fontBold: true, backgroundColor: 'E8F6F3');
        $moneyStyle = new Style(format: '#,##0');
        $dateStyle = new Style(format: 'dd/mm/yyyy hh:mm');
        $dayStyle = new Style(format: 'dd/mm/yyyy');

        $summary = $writer->getCurrentSheet()->setName('Ringkasan');
        $summary->setColumnWidth(30, 1);
        $summary->setColumnWidth(22, 2);
        $writer->addRow(Row::fromValuesWithStyle(['Laporan Laundry Pos'], $titleStyle, 26));
        $writer->addRow(Row::fromValues(['Periode', $filters['from'].' s.d. '.$filters['to']]));
        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValuesWithStyle(['Metrik', 'Nilai (Rp)'], $headerStyle));
        foreach ([['Total penjualan', $sales], ['Pembayaran diterima', $received], ['Piutang', (int) $receivables], ['Expense', $expenseTotal], ['Estimasi net profit', $received - $expenseTotal]] as [$label, $value]) {
            $writer->addRow(Row::fromValuesWithStyles([$label, (int) $value], [$labelStyle, $moneyStyle]));
        }

        $transactionSheet = $writer->addNewSheetAndMakeItCurrent()->setName('Transaksi');
        foreach ([1 => 20, 2 => 22, 3 => 22, 4 => 20, 5 => 16, 6 => 16, 7 => 16, 8 => 16, 9 => 16, 10 => 18] as $column => $width) {
            $transactionSheet->setColumnWidth($width, $column);
        }
        $writer->addRow(Row::fromValuesWithStyle(['Nomor', 'Tanggal', 'Outlet', 'Pelanggan', 'Kasir', 'Status order', 'Status bayar', 'Total', 'Dibayar', 'Sisa'], $headerStyle, 22));
        foreach ((clone $allOrders)->oldest()->get() as $order) {
            $writer->addRow(Row::fromValuesWithStyles([
                $order->number, $order->created_at->toDateTimeImmutable(), $order->outlet->name, $order->customer_name, $order->user->name,
                __('status.'.$order->status), __('status.'.$order->payment_status), $order->total, $order->paid_amount, $order->balance,
            ], [null, $dateStyle, null, null, null, null, null, $moneyStyle, $moneyStyle, $moneyStyle]));
        }

        $expenseSheet = $writer->addNewSheetAndMakeItCurrent()->setName('Expense');
        foreach ([1 => 15, 2 => 22, 3 => 22, 4 => 18, 5 => 18, 6 => 42, 7 => 16] as $column => $width) {
            $expenseSheet->setColumnWidth($width, $column);
        }
        $writer->addRow(Row::fromValuesWithStyle(['Tanggal', 'Outlet', 'Kategori', 'Metode', 'Dicatat oleh', 'Keterangan', 'Nominal'], $headerStyle, 22));
        foreach ((clone $expenses)->oldest('expense_date')->get() as $expense) {
            $writer->addRow(Row::fromValuesWithStyles([
                $expense->expense_date->toDateTimeImmutable(), $expense->outlet->name, $expense->category, strtoupper($expense->payment_method), $expense->user->name, $expense->description ?? '', $expense->amount,
            ], [$dayStyle, null, null, null, null, null, $moneyStyle]));
        }

        $attendanceSheet = $writer->addNewSheetAndMakeItCurrent()->setName('Absensi');
        foreach ([1 => 15, 2 => 24, 3 => 22, 4 => 15, 5 => 15, 6 => 15, 7 => 18, 8 => 16] as $column => $width) {
            $attendanceSheet->setColumnWidth($width, $column);
        }
        $writer->addRow(Row::fromValuesWithStyle(['Tanggal', 'Karyawan', 'Outlet', 'Jadwal masuk', 'Jam masuk', 'Jam keluar', 'Durasi kerja', 'Status'], $headerStyle, 22));
        foreach ((clone $attendances)->oldest('attendance_date')->oldest('check_in_at')->get() as $attendance) {
            $duration = $attendance->work_duration_minutes === null
                ? ''
                : intdiv($attendance->work_duration_minutes, 60).' jam '.($attendance->work_duration_minutes % 60).' menit';
            $writer->addRow(Row::fromValuesWithStyles([
                $attendance->attendance_date->toDateTimeImmutable(),
                $attendance->user->name,
                $attendance->outlet->name,
                $attendance->user->schedule?->start_time ?? '-',
                $attendance->check_in_at?->format('H:i') ?? '-',
                $attendance->check_out_at?->format('H:i') ?? '-',
                $duration,
                $attendance->status === 'late' ? 'Terlambat' : 'Hadir',
            ], [$dayStyle, null, null, null, null, null, null, null]));
        }
        $writer->close();

        return response()->download($path, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])->deleteFileAfterSend(true);
    }
}
