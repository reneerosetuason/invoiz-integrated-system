import 'package:flutter/material.dart';
import 'package:flutter/cupertino.dart';
import '../widgets/invoiz_logo.dart';
import 'home_screen.dart';

class SplashScreen extends StatefulWidget {
  const SplashScreen({super.key});

  @override
  State<SplashScreen> createState() => _SplashScreenState();
}

class _SplashScreenState extends State<SplashScreen> {
  @override
  void initState() {
    super.initState();
    _go();
  }

  Future<void> _go() async {
    await Future.delayed(const Duration(milliseconds: 1300));
    if (!mounted) return;
    // App always opens as a guest. Login / Register are available
    // from the sidebar menu and the home screen.
    Navigator.pushReplacement(
      context,
      MaterialPageRoute(builder: (_) => const HomeScreen()),
    );
  }

  @override
  Widget build(BuildContext context) {
    return CupertinoPageScaffold(
      backgroundColor: Colors.white,
      child: SafeArea(
        child: Center(
          child: Hero(
            tag: 'invoiz_logo',
            child: InvoizLogo.logoWidget(size: 140, radius: 32),
          ),
        ),
      ),
    );
  }
}
