import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../core/theme/app_colors.dart';
import '../../models/complaint_model.dart';
import '../../providers/complaint_provider.dart';
import 'complaint_form_screen.dart';

class SupportCenterScreen extends StatefulWidget {
  const SupportCenterScreen({super.key});

  @override
  State<SupportCenterScreen> createState() => _SupportCenterScreenState();
}

class _SupportCenterScreenState extends State<SupportCenterScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<ComplaintProvider>().loadComplaints();
    });
  }

  @override
  Widget build(BuildContext context) {
    final complaints = context.watch<ComplaintProvider>().complaints;
    return Scaffold(
      appBar: AppBar(title: const Text('مركز الدعم')),
      body: ListView(padding: const EdgeInsets.all(18), children: [
        Container(
          padding: const EdgeInsets.all(18),
          decoration: BoxDecoration(borderRadius: BorderRadius.circular(26), gradient: const LinearGradient(colors: [AppColors.primary, AppColors.secondary])),
          child: const Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Icon(Icons.support_agent_rounded, color: Colors.white, size: 36),
            SizedBox(height: 12),
            Text('تحتاج مساعدة؟', style: TextStyle(color: Colors.white, fontSize: 26, fontWeight: FontWeight.w900)),
            SizedBox(height: 6),
            Text('اتصل بالدعم أو أرسل شكوى وتابع حالتها.', style: TextStyle(color: Colors.white70, height: 1.35)),
          ]),
        ),
        const SizedBox(height: 16),
        _SupportTile(icon: Icons.phone_rounded, title: 'الاتصال بالدعم', subtitle: '0980906963', onTap: () {}),
        _SupportTile(icon: Icons.report_problem_outlined, title: 'إرسال شكوى', subtitle: 'مشكلة بالحجز أو الدفع أو الصالة أو الخدمة أو غير ذلك', onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const ComplaintFormScreen()))),
        const SizedBox(height: 18),
        Text('شكاواي', style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w900)),
        const SizedBox(height: 10),
        if (complaints.isEmpty)
          Container(
            padding: const EdgeInsets.all(18),
            decoration: BoxDecoration(color: AppColors.surface, borderRadius: BorderRadius.circular(22)),
            child: const Text('لا توجد شكاوى بعد. عند إرسال شكوى ستظهر هنا مع حالتها.', style: TextStyle(color: AppColors.textSecondary, height: 1.4)),
          )
        else
          ...complaints.map((complaint) => _ComplaintCard(complaint: complaint)),
      ]),
    );
  }
}

class _SupportTile extends StatelessWidget {
  final IconData icon;
  final String title;
  final String subtitle;
  final VoidCallback onTap;
  const _SupportTile({required this.icon, required this.title, required this.subtitle, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      decoration: BoxDecoration(color: AppColors.surface, borderRadius: BorderRadius.circular(20)),
      child: ListTile(
        leading: CircleAvatar(backgroundColor: AppColors.surface2, child: Icon(icon, color: AppColors.primary)),
        title: Text(title, style: const TextStyle(fontWeight: FontWeight.w900)),
        subtitle: Text(subtitle, style: const TextStyle(color: AppColors.textSecondary)),
        trailing: const Icon(Icons.arrow_forward_ios_rounded, size: 16),
        onTap: onTap,
      ),
    );
  }
}

class _ComplaintCard extends StatelessWidget {
  final ComplaintModel complaint;
  const _ComplaintCard({required this.complaint});

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(color: AppColors.surface, borderRadius: BorderRadius.circular(20)),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [
          Expanded(child: Text('${complaint.referenceNumber} • ${complaint.subject}', style: const TextStyle(fontWeight: FontWeight.w900))),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 5),
            decoration: BoxDecoration(color: AppColors.primary.withOpacity(.18), borderRadius: BorderRadius.circular(20)),
            child: Text(complaint.status.label, style: const TextStyle(color: AppColors.primary, fontSize: 12, fontWeight: FontWeight.w900)),
          ),
        ]),
        const SizedBox(height: 6),
        Text(complaint.type, style: const TextStyle(color: AppColors.textSecondary)),
        const SizedBox(height: 4),
        Text(complaint.description, style: const TextStyle(height: 1.45)),
      ]),
    );
  }
}
