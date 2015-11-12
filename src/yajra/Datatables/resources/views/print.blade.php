<table>
@foreach($data as $row)
    <tr>
        @foreach($row as $key => $value)
            @if (is_string($value) || trim($value)==='')
                <td>{{ $value }}</td>
            @endif
        @endforeach
    </tr>
@endforeach
</table>
