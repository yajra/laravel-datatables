<!DOCTYPE html>
<html lang="en">
    <head>
        <title>Print Table</title>
        <meta charset="UTF-8">
        <meta name=description content="">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <!-- Bootstrap CSS -->
        <link href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.5/css/bootstrap.min.css" rel="stylesheet" media="screen">
        <style>
            body {margin: 20px}
        </style>
    </head>
    <body>
        <table class="table table-bordered table-condensed">
            @foreach($data as $row)
                <tr>
                    @foreach($row as $key => $value)
                        @if (is_array($value))
                            <td>{{ implode(",", $value) }}</td>
                        @else
                            <td>{!! $value !!}</td>
                        @endif
                    @endforeach
                </tr>
            @endforeach
        </table>
    </body>
</html>
