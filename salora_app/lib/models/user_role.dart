enum UserRole { customer, provider, owner, admin }

extension UserRoleX on UserRole {
  String get label {
    switch (this) {
      case UserRole.customer:
        return 'عميل';
      case UserRole.provider:
        return 'مقدم خدمة';
      case UserRole.owner:
        return 'مالك صالة';
      case UserRole.admin:
        return 'مدير النظام';
    }
  }

  String get arabicLabel => label;
}
