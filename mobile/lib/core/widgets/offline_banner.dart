import 'package:flutter/material.dart';

/// Offline connectivity banner placeholder.
/// Uses a simple approach without connectivity_plus to avoid build issues.
class OfflineBanner extends StatelessWidget {
  const OfflineBanner({super.key});

  @override
  Widget build(BuildContext context) {
    // Connectivity detection removed to simplify builds.
    // Returns empty widget; can be re-enabled with connectivity_plus later.
    return const SizedBox.shrink();
  }
}

/*
// Full implementation for reference (requires connectivity_plus):
Widget _buildBanner(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.symmetric(vertical: 8, horizontal: 16),
      color: Theme.of(context).colorScheme.error,
      child: Row(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Icon(
            Icons.wifi_off,
            size: 16,
            color: Theme.of(context).colorScheme.onError,
          ),
          const SizedBox(width: 8),
          Text(
            'No internet connection',
            style: TextStyle(
              color: Theme.of(context).colorScheme.onError,
              fontSize: 13,
              fontWeight: FontWeight.w500,
            ),
          ),
        ],
      ),
    );
  }
*/
