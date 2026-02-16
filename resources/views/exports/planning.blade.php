<table>
    <thead>
        <tr>
            <th colspan="{{ count($dates) + 3 }}"
                style="font-weight: bold; font-size: 16px; text-align: center; background-color: #D60000; color: #ffffff;">
                Planning de Diffusion: {{ $campaign->spot }}
            </th>
        </tr>
        <tr>
            <th colspan="{{ count($dates) + 3 }}"
                style="text-align: center; background-color: #D60000; color: #ffffff;">
                Client: {{ $campaign->client->name ?? 'N/A' }} | Période: {{ $dates[0]->format('d/m/Y') }} -
                {{ end($dates)->format('d/m/Y') }}
            </th>
        </tr>
        <tr></tr> <!-- Empty row -->
        <tr>
            <th style="background-color: #D60000; color: #ffffff; font-weight: bold; border: 1px solid #000000;">Time
            </th>
            @foreach($dates as $date)
                <th
                    style="background-color: #D60000; color: #ffffff; font-weight: bold; border: 1px solid #000000; text-align: center;">
                    {{ $date->isoFormat('ddd DD/MM') }}
                </th>
            @endforeach
            <th style="background-color: #D60000; color: #ffffff; font-weight: bold; border: 1px solid #000000;">Total
                Passage</th>
        </tr>
    </thead>
    <tbody>
        @foreach($timeSlots as $time)
            <tr>
                <td style="font-weight: bold; border: 1px solid #000000;">{{ $time }}</td>
                @php $rowTotal = 0; @endphp
                @foreach($dates as $date)
                    @php
                        $dateStr = $date->format('Y-m-d');
                        $exists = $plannings->contains(function ($p) use ($dateStr, $time) {
                            return $p->date === $dateStr && str_starts_with($p->heure, $time);
                        });
                        if ($exists)
                            $rowTotal++;
                    @endphp
                    <td style="border: 1px solid #000000; text-align: center;">
                        @if($exists)
                            {{ $campaign->duree }}"
                        @endif
                    </td>
                @endforeach
                <td style="border: 1px solid #000000; text-align: center; font-weight: bold;">
                    {{ $rowTotal }}
                </td>
            </tr>
        @endforeach
    </tbody>
</table>