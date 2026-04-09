<table>
    <thead>
        <!-- Header Branded Section - One Bloc -->
        <tr>
            <th colspan="6" style="text-align: center; vertical-align: middle;">
                <img src="C:/Users/ganou/.gemini/antigravity/brain/24e7c58b-0b18-4c51-84a4-4edfb0d739fb/media__1772098073459.png" width="800" height="80">
            </th>
        </tr>
        <tr></tr>

        <!-- Date and Recipient Section -->
        <tr>
            <td colspan="4"></td>
            <td colspan="2" style="text-align: right; font-weight: bold; font-size: 11pt;">Tunis le {{ now()->locale('fr')->isoFormat('DD/MM/YYYY') }}</td>
        </tr>
        
        <tr>
            <!-- Les Formats Box -->
            <td colspan="2" style="border: 2px solid #000000; vertical-align: top; padding: 5px;">
                <strong>Les formats:</strong><br>
                Date: &lt;jj/mm/aaaa&gt;<br>
                Heure: &lt;hh:mm&gt;<br>
                Durée: &lt;nombre&gt;
            </td>
            <td></td>
            <!-- Recipient Box -->
            <td colspan="3" style="border: 4px double #000000; padding: 10px; vertical-align: top;">
                <strong>A l'attention de Mr. {{ $campaign->client->contact_name ?? '' }}</strong><br>
                <strong>{{ $campaign->client->name ?? '' }}</strong><br>
                Email: {{ $campaign->client->email ?? '' }}<br>
                Tél. {{ $campaign->client->telephone ?? '' }}<br>
                <div style="border-top: 1px solid #000000; margin-top: 5px;">
                    <strong>Commercial : {{ $campaign->creator->name ?? 'Foued Ben Khelifa' }}</strong>
                </div>
            </td>
        </tr>
        <tr></tr>

        <!-- Campaign Info -->
        <tr>
            <td colspan="6" style="font-weight: bold; font-size: 12pt;">Campagne: {{ $campaign->spot }}</td>
        </tr>
        <tr>
            <td colspan="6" style="font-weight: bold; font-size: 11pt; border-bottom: 2px solid #000000;">
                Période: Du {{ \Carbon\Carbon::parse($campaign->date_debut)->locale('fr')->isoFormat('DD/MM') }} au {{ \Carbon\Carbon::parse($campaign->date_fin)->locale('fr')->isoFormat('DD/MM') }}
            </td>
        </tr>
        <tr></tr>

        <!-- Main Planning Table Header -->
        <tr>
            <th style="border: 2px solid #000000; font-weight: bold; text-align: center; background-color: #F2F2F2; width: 30pt;"></th>
            <th style="border: 2px solid #000000; font-weight: bold; text-align: center; background-color: #F2F2F2; width: 150pt;">Date</th>
            <th style="border: 2px solid #000000; font-weight: bold; text-align: center; background-color: #F2F2F2; width: 60pt;">Heure</th>
            <th style="border: 2px solid #000000; font-weight: bold; text-align: center; background-color: #F2F2F2; width: 200pt;">Spot</th>
            <th style="border: 2px solid #000000; font-weight: bold; text-align: center; background-color: #F2F2F2; width: 50pt;">Durée</th>
            <th style="border: 2px solid #000000; font-weight: bold; text-align: center; background-color: #F2F2F2; width: 80pt;">Prix HT</th>
        </tr>
    </thead>
    <tbody>
        @php $totalHT = 0; @endphp
        @foreach($plannings as $index => $planning)
            @php $totalHT += $planning->prix_HT; @endphp
            <tr>
                <td style="border: 1px solid #000000; text-align: center; background-color: #D9D9D9; font-weight: bold;">{{ $index + 1 }}</td>
                <td style="border: 1px solid #000000; text-align: center;">
                    {{ \Carbon\Carbon::parse($planning->date)->locale('fr')->isoFormat('dddd D MMMM YYYY') }}
                </td>
                <td style="border: 1px solid #000000; text-align: center;">{{ substr($planning->heure, 0, 5) }}</td>
                @if($index === 0)
                    <td rowspan="{{ count($plannings) }}" style="border: 1px solid #000000; text-align: center; vertical-align: middle; wrap-text: true; font-weight: bold;">
                        Campagne: {{ $campaign->spot }}
                    </td>
                @endif
                <td style="border: 1px solid #000000; text-align: center; font-weight: bold;">{{ $planning->duree }}</td>
                <td style="border: 1px solid #000000; text-align: right; padding-right: 5px;">{{ number_format($planning->prix_HT, 3, ',', ' ') }}</td>
            </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td colspan="5" style="border: 2px solid #000000; text-align: center; font-weight: bold; font-size: 11pt;">Montant HT</td>
            <td style="border: 2px solid #000000; text-align: right; font-weight: bold; font-size: 11pt;">{{ number_format($totalHT, 3, ',', ' ') }}</td>
        </tr>
    </tfoot>
</table>
