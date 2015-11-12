<table>
    @foreach($data as $row)
        @if ($row == reset($data)) 
            <tr>
                @foreach($row as $key => $value)
                    <th>{!! $key !!}</th>
                @endforeach
            </tr>
        @endif
        <tr>
            @foreach($row as $key => $value)
                @if (is_string($value) || trim($value)==='' || is_numeric($value))
                    <td>{!! $value !!}</td>
                @endif
            @endforeach
        </tr>
    @endforeach
</table>
