<table>
    <thead>
        <tr>
            <th colspan="5"
                style="font-weight: bold; font-size: 16px; text-align: center; background-color: #2d5f3f; color: #ffffff;">
                Conducteur de Diffusion
            </th>
        </tr>
        <tr>
            <th colspan="5" style="text-align: center; background-color: #2d5f3f; color: #ffffff;">
                Date: {{ \Carbon\Carbon::parse($conducteur->date)->isoFormat('dddd D MMMM YYYY') }}
            </th>
        </tr>
        <tr></tr> <!-- Empty row -->
        <tr>
            <th style="background-color: #2d5f3f; color: #ffffff; font-weight: bold; border: 1px solid #000000;">Heures
            </th>
            <th style="background-color: #2d5f3f; color: #ffffff; font-weight: bold; border: 1px solid #000000;">
                Annonceur</th>
            <th style="background-color: #2d5f3f; color: #ffffff; font-weight: bold; border: 1px solid #000000;">Spots
            </th>
            <th
                style="background-color: #2d5f3f; color: #ffffff; font-weight: bold; border: 1px solid #000000; text-align: center;">
                Durée</th>
            <th
                style="background-color: #2d5f3f; color: #ffffff; font-weight: bold; border: 1px solid #000000; text-align: center;">
                Numéro</th>
        </tr>
    </thead>
    <tbody>
        @foreach($timeSlots as $time)
            @php 
                $entries = $schedule[$time] ?? [null]; 
                $hasData = isset($schedule[$time]);
            @endphp
            @foreach($entries as $index => $entry)
                <tr style="{{ $hasData ? 'background-color: #f0f9f4;' : '' }}">
                    <td style="border: 1px solid #000000; font-family: 'Courier New', monospace;">
                        {{ $index === 0 ? $time : '' }}
                    </td>
                    <td style="border: 1px solid #000000;">{{ $entry['annonceur'] ?? '' }}</td>
                    <td style="border: 1px solid #000000;">{{ $entry['spot'] ?? '' }}</td>
                    <td style="border: 1px solid #000000; text-align: center;">{{ $entry['duree'] ?? '' }}</td>
                    <td style="border: 1px solid #000000; text-align: center; font-family: 'Courier New', monospace;">
                        {{ $entry['numero'] ?? '' }}
                    </td>
                </tr>
            @endforeach
        @endforeach
    </tbody>
</table>