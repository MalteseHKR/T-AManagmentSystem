<form method="GET" action="{{ $route }}" class="mb-4">
    <div class="row">
        @if($hasNameFilter)
        <div class="col-md-{{ $columns }}">
            <div class="form-group mb-2">
                <label for="name">{{ $nameLabel }}</label>
                <input type="text" name="name" id="name" class="form-control" placeholder="{{ $namePlaceholder }}" value="{{ request('name') }}">
            </div>
        </div>
        @endif

        @if($hasDepartmentFilter)
        <div class="col-md-{{ $columns }}">
            <div class="form-group mb-2">
                <label for="department">Department</label>
                <select name="department" id="department" class="form-control">
                    <option value="">All Departments</option>
                    @foreach($departments as $dept)
                        <option value="{{ $dept }}" {{ request('department') == $dept ? 'selected' : '' }}>{{ $dept }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        @endif

        @if($hasDateFilter)
        <div class="col-md-{{ $columns }}">
            <div class="form-group mb-2">
                <label for="date_from">From Date</label>
                <input type="date" name="date_from" id="date_from" class="form-control" value="{{ request('date_from') }}">
            </div>
        </div>
        <div class="col-md-{{ $columns }}">
            <div class="form-group mb-2">
                <label for="date_to">To Date</label>
                <input type="date" name="date_to" id="date_to" class="form-control" value="{{ request('date_to') }}">
            </div>
        </div>
        @endif

        {{ $slot }}

        <div class="col-md-{{ $columns }}">
            <div class="form-group mb-2">
                <label class="d-block">&nbsp;</label>
                <button type="submit" class="btn btn-primary w-100">Filter</button>
                <a href="{{ $route }}" class="btn btn-secondary w-100 mt-2">Clear</a>
            </div>
        </div>
    </div>
</form>