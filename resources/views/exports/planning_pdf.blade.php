<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <style>
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 11px;
            color: #333;
            margin: 0;
            padding: 0;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .logo-cell {
            width: 20%;
            vertical-align: top;
        }
        .company-info {
            width: 60%;
            text-align: center;
            font-size: 10px;
            color: #666;
        }
        .date-location {
            width: 100%;
            text-align: right;
            margin-bottom: 10px;
            font-weight: bold;
        }
        .recipient-box {
            width: 50%;
            float: right;
            border: 1px solid #000;
            padding: 8px;
            margin-bottom: 20px;
        }
        .recipient-line {
            margin-bottom: 4px;
        }
        .info-box {
            border: 2px solid #000;
            padding: 5px;
            width: 30%;
            margin-bottom: 20px;
            display: inline-block;
        }
        .campaign-title {
            clear: both;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .period {
            font-weight: bold;
            margin-bottom: 20px;
        }
        .planning-table {
            width: 100%;
            border-collapse: collapse;
            border: 2px solid #000;
        }
        .planning-table th, .planning-table td {
            border: 1px solid #000;
            padding: 6px;
            text-align: center;
        }
        .planning-table th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        .footer-table {
            width: 100%;
            margin-top: 20px;
            border-collapse: collapse;
        }
        .footer-table td {
            border: 1px solid #000;
            padding: 8px;
            font-weight: bold;
        }
        .total-label {
            width: 80%;
            text-align: center;
        }
        .total-value {
            width: 20%;
            text-align: right;
        }
        .page-number {
            text-align: center;
            font-size: 40px;
            color: #ddd;
            position: absolute;
            top: 40%;
            left: 40%;
            z-index: -1;
            transform: rotate(-45deg);
        }
        .logo-img {
            height: 40px;
        }
    </style>
</head>
<body>
    <table class="header-table">
        <tr>
            <td class="logo-cell">
                <img src="{{ public_path('assets/logo-mfm.png') }}" class="logo-img">
            </td>
            <td class="company-info">
                <strong>MED TELECOMS SARL - Radio Mosaique: Immb Mosaique, rue Echabia 1073 Tunis Montplaisir</strong><br>
                Tél: 71 113 001/Fax: 71 905 316 - RC: B149042003 - MF: 853067NAM00
            </td>
            <td class="logo-cell" style="text-align: right;">
                <img src="{{ public_path('assets/logo-mfm.png') }}" class="logo-img">
            </td>
        </tr>
    </table>

    <div class="date-location">
        Tunis le {{ now()->format('d/m/Y') }}
    </div>

    <div class="recipient-box">
        <div class="recipient-line"><strong>A l'attention de Mr. {{ $campaign->client->contact_name ?? 'N/A' }}</strong></div>
        <div class="recipient-line">{{ $campaign->client->name ?? 'N/A' }}</div>
        <div class="recipient-line">Email: {{ $campaign->client->email ?? 'N/A' }}</div>
        <div class="recipient-line">Tél. {{ $campaign->client->telephone ?? 'N/A' }}</div>
        <div class="recipient-line" style="border-top: 1px solid #000; padding-top: 4px; margin-top: 8px;">
            Commercial : {{ $campaign->creator->name ?? 'Foued Ben Khelifa' }}
        </div>
    </div>

    <div class="info-box">
        <strong>Les formats:</strong><br>
        Date: &lt;jj/mm/aaaa&gt;<br>
        Heure: &lt;hh:mm&gt;<br>
        Durée: &lt;nombre&gt;
    </div>

    <div class="campaign-title">
        Campagne: {{ $campaign->spot }}
    </div>

    <div class="period">
        Période: Du {{ \Carbon\Carbon::parse($campaign->date_debut)->format('d/m') }} au {{ \Carbon\Carbon::parse($campaign->date_fin)->format('d/m') }}
    </div>

    <table class="planning-table">
        <thead>
            <tr>
                <th width="30"></th>
                <th>Date</th>
                <th>Heure</th>
                <th>Spot</th>
                <th>Durée</th>
                <th>Prix HT</th>
            </tr>
        </thead>
        <tbody>
            @foreach($plannings as $index => $planning)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ \Carbon\Carbon::parse($planning->date)->isoFormat('dddd D MMMM YYYY') }}</td>
                    <td>{{ substr($planning->heure, 0, 5) }}</td>
                    <td rowspan="{{ $index == 0 ? count($plannings) : 1 }}" style="{{ $index > 0 ? 'display: none;' : '' }}">
                        {{ $campaign->spot }}
                    </td>
                    <td>{{ $planning->duree }}</td>
                    <td>{{ number_format($planning->prix_HT, 3, ',', ' ') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="footer-table">
        <tr>
            <td class="total-label">Montant HT</td>
            <td class="total-value">{{ number_format($plannings->sum('prix_HT'), 3, ',', ' ') }}</td>
        </tr>
    </table>

    <div class="page-number">Page 1</div>

</body>
</html>
