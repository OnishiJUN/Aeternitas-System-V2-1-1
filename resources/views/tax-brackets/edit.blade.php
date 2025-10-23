@extends('layouts.dashboard-base', ['user' => $user, 'activeRoute' => 'tax-brackets.index'])

@section('title', 'Edit Tax Bracket')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Edit Tax Bracket</h1>
            <p class="mt-1 text-sm text-gray-500">Update tax bracket: {{ $taxBracket->name }}</p>
        </div>
        <div class="mt-4 sm:mt-0">
            <a href="{{ route('tax-brackets.index') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
                <i class="fas fa-arrow-left mr-2"></i>
                Back to Tax Brackets
            </a>
        </div>
    </div>

    <!-- Form -->
    <div class="max-w-4xl">
        <div class="bg-white rounded-lg shadow-sm border border-gray-200">
            <form method="POST" action="{{ route('tax-brackets.update', $taxBracket) }}" class="p-6 space-y-6">
                @csrf
                @method('PUT')
                
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Basic Information -->
                    <div class="space-y-4">
                        <div class="bg-gray-50 rounded-lg p-4">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">
                                <i class="fas fa-info-circle mr-2 text-blue-600"></i>
                                Basic Information
                            </h3>
                            
                            <!-- Name -->
                            <div class="mb-4">
                                <label for="name" class="block text-sm font-medium text-gray-700 mb-2">Bracket Name</label>
                                <input type="text" name="name" id="name" required 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('name') border-red-500 @enderror" 
                                       placeholder="e.g., 15% Bracket, Exempt Bracket"
                                       value="{{ old('name', $taxBracket->name) }}">
                                @error('name')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Description -->
                            <div class="mb-4">
                                <label for="description" class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                                <textarea name="description" id="description" rows="3" 
                                          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('description') border-red-500 @enderror" 
                                          placeholder="Optional description for this tax bracket">{{ old('description', $taxBracket->description) }}</textarea>
                                @error('description')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Sort Order -->
                            <div class="mb-4">
                                <label for="sort_order" class="block text-sm font-medium text-gray-700 mb-2">Sort Order</label>
                                <input type="number" name="sort_order" id="sort_order" required min="1"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('sort_order') border-red-500 @enderror" 
                                       placeholder="1"
                                       value="{{ old('sort_order', $taxBracket->sort_order) }}">
                                @error('sort_order')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                    
                    <!-- Tax Configuration -->
                    <div class="space-y-4">
                        <div class="bg-gray-50 rounded-lg p-4">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">
                                <i class="fas fa-calculator mr-2 text-green-600"></i>
                                Tax Configuration
                            </h3>
                            
                            <!-- Income Range -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                                <div>
                                    <label for="min_income" class="block text-sm font-medium text-gray-700 mb-2">Minimum Income (₱)</label>
                                    <input type="number" name="min_income" id="min_income" required min="0" step="0.01"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('min_income') border-red-500 @enderror" 
                                           placeholder="0.00"
                                           value="{{ old('min_income', $taxBracket->min_income) }}">
                                    @error('min_income')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label for="max_income" class="block text-sm font-medium text-gray-700 mb-2">Maximum Income (₱)</label>
                                    <input type="number" name="max_income" id="max_income" min="0" step="0.01"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('max_income') border-red-500 @enderror" 
                                           placeholder="Leave empty for no limit"
                                           value="{{ old('max_income', $taxBracket->max_income) }}">
                                    @error('max_income')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                    <p class="text-xs text-gray-500 mt-1">Leave empty for highest bracket</p>
                                </div>
                            </div>

                            <!-- Tax Rate -->
                            <div class="mb-4">
                                <label for="tax_rate" class="block text-sm font-medium text-gray-700 mb-2">Tax Rate (%)</label>
                                <input type="number" name="tax_rate" id="tax_rate" required min="0" max="100" step="0.01"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('tax_rate') border-red-500 @enderror" 
                                       placeholder="15.00"
                                       value="{{ old('tax_rate', $taxBracket->tax_rate) }}">
                                @error('tax_rate')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Base Tax -->
                            <div class="mb-4">
                                <label for="base_tax" class="block text-sm font-medium text-gray-700 mb-2">Base Tax Amount (₱)</label>
                                <input type="number" name="base_tax" id="base_tax" required min="0" step="0.01"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('base_tax') border-red-500 @enderror" 
                                       placeholder="0.00"
                                       value="{{ old('base_tax', $taxBracket->base_tax) }}">
                                @error('base_tax')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Excess Over -->
                            <div class="mb-4">
                                <label for="excess_over" class="block text-sm font-medium text-gray-700 mb-2">Excess Over Amount (₱)</label>
                                <input type="number" name="excess_over" id="excess_over" required min="0" step="0.01"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('excess_over') border-red-500 @enderror" 
                                       placeholder="0.00"
                                       value="{{ old('excess_over', $taxBracket->excess_over) }}">
                                @error('excess_over')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                                <p class="text-xs text-gray-500 mt-1">Amount to subtract from income before applying tax rate</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Status and Dates -->
                <div class="bg-gray-50 rounded-lg p-4">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">
                        <i class="fas fa-calendar mr-2 text-purple-600"></i>
                        Status and Effective Dates
                    </h3>
                    
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                        <!-- Active Status -->
                        <div>
                            <div class="flex items-center">
                                <input type="checkbox" name="is_active" id="is_active" value="1" 
                                       class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" 
                                       {{ old('is_active', $taxBracket->is_active) ? 'checked' : '' }}>
                                <label for="is_active" class="ml-2 text-sm font-medium text-gray-700">
                                    Active
                                </label>
                            </div>
                        </div>

                        <!-- Effective From -->
                        <div>
                            <label for="effective_from" class="block text-sm font-medium text-gray-700 mb-2">Effective From</label>
                            <input type="date" name="effective_from" id="effective_from"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('effective_from') border-red-500 @enderror" 
                                   value="{{ old('effective_from', $taxBracket->effective_from?->format('Y-m-d')) }}">
                            @error('effective_from')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Effective Until -->
                        <div>
                            <label for="effective_until" class="block text-sm font-medium text-gray-700 mb-2">Effective Until</label>
                            <input type="date" name="effective_until" id="effective_until"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('effective_until') border-red-500 @enderror" 
                                   value="{{ old('effective_until', $taxBracket->effective_until?->format('Y-m-d')) }}">
                            @error('effective_until')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                            <p class="text-xs text-gray-500 mt-1">Leave empty for no expiration</p>
                        </div>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="flex justify-end space-x-3 pt-6 border-t border-gray-200">
                    <a href="{{ route('tax-brackets.index') }}" class="px-4 py-2 border border-gray-300 rounded-lg font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
                        Cancel
                    </a>
                    <button type="submit" class="px-4 py-2 bg-blue-600 border border-transparent rounded-lg font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
                        <i class="fas fa-save mr-2"></i>
                        Update Tax Bracket
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
